<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Services\PaymentService;
use App\Support\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_mode_checkout_uses_the_oxapay_sandbox_page(): void
    {
        $merchant = Merchant::factory()->create(['business_name' => 'Ali Electronics', 'brand_color' => '#0ea5e9']);
        $payment = app(PaymentService::class)->create($merchant, 'test', ['amount' => '10', 'description' => 'Headphones']);

        $this->get(route('checkout.show', $payment))
            ->assertOk()
            ->assertSee('Ali Electronics')
            ->assertSee('Headphones')
            ->assertSee('10.00 USD')
            ->assertSee('Test mode')
            ->assertSee('--brand: #0ea5e9', false)
            ->assertDontSee('Choose how to pay');

        Http::fake(['api.oxapay.com/v1/payment/invoice' => Http::response($this->oxapay([
            'track_id' => '9001', 'payment_url' => 'https://pay.oxapay.com/1/9001', 'expired_at' => now()->addHour()->timestamp, 'date' => now()->timestamp,
        ]))]);

        $this->post(route('checkout.start', $payment))->assertRedirect('https://pay.oxapay.com/1/9001');

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.oxapay.com/v1/payment/invoice'
            && $r->hasHeader('merchant_api_key', 'test-merchant-key')
            && $r['sandbox'] === true
            && $r['amount'] === 10.0
            && $r['order_id'] === $payment->public_id
            && $r['return_url'] === route('checkout.show', $payment)
            && $r['callback_url'] === 'https://gateway.test/oxapay/webhook'
            && $r['description'] === 'Ali Electronics: Headphones'
            && ! isset($r['to_currency']));

        $payment->refresh();
        $this->assertSame(['invoice', '9001', 'new'], [$payment->type, $payment->track_id, $payment->status]);
    }

    public function test_live_custom_checkout_creates_a_white_label_payment_converted_to_usdt(): void
    {
        $merchant = Merchant::factory()->live()->create();
        $payment = app(PaymentService::class)->create($merchant, 'live', ['amount' => '10']);
        $this->fakeLiveOxaPay();

        $this->get(route('checkout.show', $payment))
            ->assertOk()
            ->assertSee('Choose how to pay')
            ->assertSee('value="BTC"', false)
            ->assertSee('Tron Network')
            ->assertDontSee('value="ETH"', false);

        $this->post(route('checkout.start', $payment), ['pay_currency' => 'USDT'])->assertSessionHasErrors('network');

        $this->post(route('checkout.start', $payment), ['pay_currency' => 'BTC'])->assertRedirect(route('checkout.show', $payment));

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'payment/white-label')
            && $r['pay_currency'] === 'BTC'
            && $r['network'] === 'Bitcoin'
            && $r['to_currency'] === 'USDT'
            && ! isset($r['sandbox']));

        $payment->refresh();
        $this->assertSame('white_label', $payment->type);
        $this->assertSame('0.0001207515', $payment->pay_amount);

        $this->get(route('checkout.show', $payment))
            ->assertOk()
            ->assertSee('Send exactly')
            ->assertSeeText('0.0001207515 BTC')
            ->assertSee('data-copy="bc1qexampleaddress"', false)
            // Our own QR of the plain address, not OxaPay's payment-URI image (exchanges reject "bitcoin:...?amount=").
            ->assertSee('<svg xmlns="http://www.w3.org/2000/svg"', false)
            ->assertDontSee('api.qrserver.com')
            ->assertDontSee('bitcoin:bc1qexampleaddress')
            ->assertSee('Address only. Enter the amount yourself.')
            ->assertSee('withdrawal fee comes on top');
    }

    public function test_qr_codes_contain_only_the_plain_address(): void
    {
        $svg = QrCode::svg('TXpPgM21WxFxjGhwH1evjuSPFH4SEdgqwo');

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringNotContainsString('<?xml', $svg);
        $this->assertStringNotContainsString('tron:', $svg);

        // Same text in, same QR out; different text, different QR.
        $this->assertSame($svg, QrCode::svg('TXpPgM21WxFxjGhwH1evjuSPFH4SEdgqwo'));
        $this->assertNotSame($svg, QrCode::svg('TQ7m2wYourTronAddressHere123456789'));
    }

    public function test_paying_in_usdt_is_not_converted(): void
    {
        $merchant = Merchant::factory()->live()->create();
        $payment = app(PaymentService::class)->create($merchant, 'live', ['amount' => '10']);
        $this->fakeLiveOxaPay();

        $this->post(route('checkout.start', $payment), ['pay_currency' => 'USDT', 'network' => 'Tron'])->assertRedirect();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'payment/white-label') && $r['network'] === 'Tron' && ! isset($r['to_currency']));
    }

    public function test_paid_status_credits_the_merchant_and_sends_a_signed_webhook(): void
    {
        $merchant = Merchant::factory()->create(['webhook_url' => 'https://merchant.test/hooks']);
        $payment = app(PaymentService::class)->create($merchant, 'test', ['amount' => '10', 'reference' => 'order_9']);
        $payment->update(['track_id' => '9001', 'type' => 'invoice', 'status' => 'new']);

        Http::fake([
            'api.oxapay.com/v1/payment/9001' => Http::response($this->oxapay(['track_id' => '9001', 'status' => 'paid', 'txs' => [['tx_hash' => 'sandbox', 'amount' => 10, 'currency' => 'USDT', 'network' => 'Tron Network']]])),
            'merchant.test/*' => Http::response('ok'),
        ]);

        $this->getJson(route('checkout.status', $payment))->assertExactJson(['status' => 'paid', 'final' => true]);
        $this->get(route('checkout.show', $payment))->assertOk()->assertSee('Payment received');

        $this->assertSame('9.40', $merchant->fresh()->test_balance);

        $secret = $merchant->fresh()->webhook_secret;
        Http::assertSent(function (Request $r) use ($secret, $payment) {
            if ($r->url() !== 'https://merchant.test/hooks') {
                return false;
            }

            $event = json_decode($r->body(), true);
            $expected = hash_hmac('sha256', $r->header('X-Webhook-Timestamp')[0].'.'.$r->body(), $secret);

            return hash_equals($expected, $r->header('X-Webhook-Signature')[0])
                && $event['type'] === 'payment.paid'
                && $event['data']['id'] === $payment->public_id
                && $event['data']['reference'] === 'order_9'
                && $event['data']['net_amount'] === '9.40';
        });

        $delivery = $merchant->webhookDeliveries()->sole();
        $this->assertSame(['succeeded', 200, 1], [$delivery->status, $delivery->response_code, $delivery->attempts]);
    }

    public function test_failed_webhook_deliveries_are_recorded_for_retry(): void
    {
        $merchant = Merchant::factory()->create(['webhook_url' => 'https://merchant.test/hooks']);
        Http::fake(['merchant.test/*' => Http::response('boom', 500)]);

        $this->actingAs($merchant->user)->post(route('developers.webhook.test'))->assertSessionHas('status');

        $delivery = $merchant->webhookDeliveries()->sole();
        $this->assertSame('test.ping', $delivery->event);
        $this->assertSame(500, $delivery->response_code);
        $this->assertSame('retrying', $delivery->status);
    }

    public function test_abandoned_payments_expire(): void
    {
        $merchant = Merchant::factory()->create(['webhook_url' => 'https://merchant.test/hooks']);
        $payment = app(PaymentService::class)->create($merchant, 'test', ['amount' => '10']);
        $payment->update(['expires_at' => now()->subMinute()]);
        Http::fake(['merchant.test/*' => Http::response('ok')]);

        $this->artisan('payments:sync')->assertSuccessful();

        $this->assertSame('expired', $payment->fresh()->status);
        $this->get(route('checkout.show', $payment))->assertSee('Payment window expired');
        $this->post(route('checkout.start', $payment))->assertRedirect(route('checkout.show', $payment));
        Http::assertSent(fn (Request $r) => json_decode($r->body(), true)['type'] === 'payment.expired');
    }

    public function test_payment_links_create_payments_for_their_merchant(): void
    {
        $merchant = Merchant::factory()->create();
        $open = PaymentLink::create(['merchant_id' => $merchant->id, 'mode' => 'test', 'title' => 'Donation']);
        $fixed = PaymentLink::create(['merchant_id' => $merchant->id, 'mode' => 'test', 'title' => 'Logo design', 'amount' => '49.99']);

        $this->get(route('links.show', $open))->assertOk()->assertSee('Donation')->assertSee('name="amount"', false);
        $this->post(route('links.pay', $open), ['amount' => '0.10'])->assertSessionHasErrors('amount');
        $this->post(route('links.pay', $open), ['amount' => '7.5', 'customer_email' => 'fan@example.com'])
            ->assertRedirect(route('checkout.show', Payment::latest('id')->first()));

        $this->post(route('links.pay', $fixed))->assertRedirect();

        $payments = Payment::orderBy('id')->get();
        $this->assertSame(['7.50', '49.99'], $payments->pluck('amount')->all());
        $this->assertSame([$open->id, $fixed->id], $payments->pluck('payment_link_id')->all());
        $this->assertSame('fan@example.com', $payments[0]->customer_email);

        $fixed->update(['active' => false]);
        $this->get(route('links.show', $fixed))->assertNotFound();

        $live = PaymentLink::create(['merchant_id' => $merchant->id, 'mode' => 'live', 'title' => 'Live link']);
        $this->get(route('links.show', $live))->assertNotFound(); // merchant not live yet
    }

    protected function fakeLiveOxaPay(): void
    {
        $tron = ['network' => 'Tron', 'name' => 'Tron Network', 'keys' => ['Tron', 'TRC20', 'TRX']];
        $bsc = ['network' => 'BSC', 'name' => 'Binance Smart Chain', 'keys' => ['BSC', 'BEP20', 'BNB']];

        Http::fake([
            'api.oxapay.com/v1/payment/accepted-currencies' => Http::response($this->oxapay(['list' => ['USDT', 'BTC']])),
            'api.oxapay.com/v1/common/currencies' => Http::response($this->oxapay([
                'BTC' => ['symbol' => 'BTC', 'name' => 'Bitcoin', 'status' => true, 'networks' => ['Bitcoin' => ['network' => 'Bitcoin', 'name' => 'Bitcoin Network', 'keys' => ['Bitcoin', 'BTC']]]],
                'ETH' => ['symbol' => 'ETH', 'name' => 'Ethereum', 'status' => true, 'networks' => ['Ethereum' => ['network' => 'Ethereum', 'name' => 'Ethereum Network', 'keys' => ['ERC20']]]],
                'USDT' => ['symbol' => 'USDT', 'name' => 'Tether', 'status' => true, 'networks' => ['Tron' => $tron, 'BSC' => $bsc]],
            ])),
            'api.oxapay.com/v1/payment/white-label' => Http::response($this->oxapay([
                'track_id' => '7001', 'amount' => 10, 'currency' => 'USD', 'pay_amount' => 0.0001207515, 'pay_currency' => 'BTC',
                'network' => 'Bitcoin Network', 'address' => 'bc1qexampleaddress', 'memo' => '', 'rate' => 82814.5,
                'qr_code' => 'https://api.qrserver.com/v1/create-qr-code/?data=bitcoin:bc1qexampleaddress', 'expired_at' => now()->addHour()->timestamp, 'date' => now()->timestamp,
            ])),
            'api.oxapay.com/v1/payment/*' => Http::response($this->oxapay(['track_id' => '7001', 'status' => 'waiting', 'txs' => []])),
        ]);
    }
}
