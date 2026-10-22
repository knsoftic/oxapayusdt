<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Payout;
use App\Services\PaymentService;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function livePayout(string $balance = '100.00', string $amount = '50'): Payout
    {
        $merchant = Merchant::factory()->live()->create(['webhook_url' => 'https://merchant.test/hooks']);
        $merchant->forceFill(['live_balance' => $balance])->save();

        return app(PayoutService::class)->request($merchant, 'live', [
            'amount' => $amount, 'network' => 'Tron', 'address' => 'TQ7m2wYourTronAddressHere123456789',
        ]);
    }

    public function test_merchant_can_request_a_withdrawal_from_the_portal(): void
    {
        $merchant = Merchant::factory()->live()->create();
        $merchant->forceFill(['live_balance' => '30.00'])->save();

        $this->actingAs($merchant->user)->post(route('mode.switch'), ['mode' => 'live']);

        $this->post(route('payouts.store'), ['amount' => '3', 'network' => 'Tron', 'address' => 'TQ7m2wYourTronAddressHere123456789'])
            ->assertSessionHasErrors(['amount' => 'The minimum withdrawal is $5.00.']);

        $this->post(route('payouts.store'), ['amount' => '30', 'network' => 'Tron', 'address' => 'TQ7m2wYourTronAddressHere123456789'])
            ->assertSessionHas('status', 'Withdrawal requested. It will be sent after review.');

        $payout = Payout::sole();
        $this->assertSame(['pending', '30.00', '1.00', '29.00'], [$payout->status, $payout->amount, $payout->fee, $payout->net_amount]);
        $this->assertSame('0.00', $merchant->fresh()->live_balance);
        $this->get(route('payouts.index'))->assertOk()->assertSee('29.00 USDT');
    }

    public function test_admin_sends_a_payout_through_oxapay_once(): void
    {
        $payout = $this->livePayout();
        Http::fake(['api.oxapay.com/v1/payout' => Http::response($this->oxapay(['track_id' => '88001', 'status' => 'processing']))]);

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.payouts.approve', $payout))->assertSessionHas('status');
        $this->post(route('admin.payouts.approve', $payout))->assertSessionHasErrors('payout'); // second click

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.oxapay.com/v1/payout'
            && $r->hasHeader('payout_api_key', 'test-payout-key')
            && ! $r->hasHeader('merchant_api_key')
            && $r['amount'] === 49.0
            && $r['currency'] === 'USDT'
            && $r['network'] === 'Tron'
            && $r['address'] === 'TQ7m2wYourTronAddressHere123456789');

        $payout->refresh();
        $this->assertSame(['processing', '88001', $admin->id], [$payout->status, $payout->track_id, $payout->processed_by]);
    }

    public function test_oxapay_refusing_a_payout_puts_it_back_in_the_queue(): void
    {
        $payout = $this->livePayout();
        Http::fake(['api.oxapay.com/v1/payout' => Http::response($this->oxapay(['message' => 'Insufficient balance.'], 400), 400)]);

        $this->actingAs($this->admin())->post(route('admin.payouts.approve', $payout))->assertSessionHasErrors('payout');

        $payout->refresh();
        $this->assertSame('pending', $payout->status);
        $this->assertStringContainsString('Insufficient balance', $payout->note);
    }

    public function test_payout_webhooks_complete_or_fail_the_payout(): void
    {
        Http::fake(['merchant.test/*' => Http::response('ok')]);

        $done = $this->livePayout();
        $done->update(['status' => 'processing', 'track_id' => '1']);
        $this->signedOxaPayWebhook(['track_id' => '1', 'status' => 'Confirmed', 'type' => 'payout', 'tx_hash' => '0xabc'], 'test-payout-key')->assertOk();
        $this->assertSame(['completed', '0xabc'], [$done->fresh()->status, $done->fresh()->tx_hash]);

        $failed = $this->livePayout();
        $failed->update(['status' => 'processing', 'track_id' => '2']);
        // Signed with the merchant key instead of the payout key: rejected.
        $this->signedOxaPayWebhook(['track_id' => '2', 'status' => 'Failed', 'type' => 'payout'])->assertStatus(400);
        $this->signedOxaPayWebhook(['track_id' => '2', 'status' => 'Failed', 'type' => 'payout'], 'test-payout-key')->assertOk();
        $this->signedOxaPayWebhook(['track_id' => '2', 'status' => 'Failed', 'type' => 'payout'], 'test-payout-key')->assertOk(); // retry

        $this->assertSame('failed', $failed->fresh()->status);
        $this->assertSame('100.00', $failed->merchant->fresh()->live_balance); // fully returned, once
        Http::assertSent(fn (Request $r) => json_decode($r->body(), true)['type'] === 'payout.failed');
    }

    public function test_admin_can_reject_or_mark_a_payout_paid(): void
    {
        Http::fake(['merchant.test/*' => Http::response('ok')]);
        $admin = $this->admin();

        $rejected = $this->livePayout();
        $this->actingAs($admin)->post(route('admin.payouts.reject', $rejected), ['reason' => 'Address on a sanctions list'])->assertSessionHas('status');
        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertSame('100.00', $rejected->merchant->fresh()->live_balance);
        $this->assertEquals(
            ['payout', 'payout_fee', 'payout_reversal', 'payout_fee_reversal'],
            $rejected->merchant->ledgerEntries()->orderBy('id')->pluck('type')->all()
        );

        $paid = $this->livePayout();
        $this->post(route('admin.payouts.paid', $paid), ['tx_hash' => 'abc123'])->assertSessionHas('status');
        $this->assertSame(['completed', 'abc123'], [$paid->fresh()->status, $paid->fresh()->tx_hash]);
        $this->post(route('admin.payouts.reject', $paid), ['reason' => 'late'])->assertSessionHasErrors('payout');
        $this->assertSame('50.00', $paid->merchant->fresh()->live_balance);
    }

    public function test_payment_webhook_credits_the_right_merchant(): void
    {
        $merchant = Merchant::factory()->create();
        $payment = app(PaymentService::class)->create($merchant, 'test', ['amount' => '20']);
        $payment->update(['track_id' => '123', 'type' => 'invoice', 'status' => 'new']);

        $this->signedOxaPayWebhook(['track_id' => '123', 'status' => 'Paid', 'type' => 'invoice', 'order_id' => $payment->public_id], 'wrong-key')->assertStatus(400);
        $this->assertSame('new', $payment->fresh()->status);

        $this->signedOxaPayWebhook(['track_id' => '123', 'status' => 'Paid', 'type' => 'invoice'])->assertOk()->assertContent('ok');
        $this->signedOxaPayWebhook(['track_id' => '123', 'status' => 'Paid', 'type' => 'invoice'])->assertOk(); // OxaPay retry

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('19.30', $merchant->fresh()->test_balance); // 20 - (0.20 + 0.50)
        $this->assertSame(2, $merchant->ledgerEntries()->count());

        $this->signedOxaPayWebhook(['track_id' => 'unknown', 'status' => 'Paid', 'type' => 'invoice'])->assertOk();
        $this->assertSame(1, Payment::count());
    }
}
