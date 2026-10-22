<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\Payment;
use App\Services\Ledger;
use App\Services\PaymentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_pay_in_fee_uses_platform_defaults_or_merchant_overrides(): void
    {
        $merchant = Merchant::factory()->create();

        // 1% + $0.50
        $this->assertSame('0.60', $merchant->payinFeeFor('10.00'));
        $this->assertSame('1.50', $merchant->payinFeeFor('100'));
        $this->assertSame('1% + $0.50', $merchant->payinFeeLabel());

        // Payout default: $1.00 fixed, no percentage.
        $this->assertSame('1.00', $merchant->payoutFeeFor('50'));
        $this->assertSame('$1.00', $merchant->payoutFeeLabel());

        $merchant->update(['fee_payin_percent' => 0, 'fee_payin_fixed' => 0.25, 'fee_payout_percent' => 2, 'fee_payout_fixed' => 2]);
        $this->assertSame('0.25', $merchant->payinFeeFor('10.00'));
        $this->assertSame('3.00', $merchant->payoutFeeFor('50')); // 2% of 50 + 2.00
        $this->assertSame('2% + $2.00', $merchant->payoutFeeLabel());

        // A platform default of 1.5% payout applies to merchants without an override.
        config(['platform.fees.payout_percent' => 1.5]);
        $default = Merchant::factory()->create();
        $this->assertSame('2.50', $default->payoutFeeFor('100')); // 1.5% of 100 + 1.00

        // The fee never exceeds the amount.
        $merchant->update(['fee_payin_fixed' => 5]);
        $this->assertSame('1.00', $merchant->payinFeeFor('1.00'));
    }

    public function test_a_paid_payment_credits_the_net_amount_exactly_once(): void
    {
        $merchant = Merchant::factory()->create();
        $payment = app(PaymentService::class)->create($merchant, 'test', ['amount' => '10']);

        $this->assertSame('0.60', $payment->fee);
        $this->assertSame('9.40', $payment->net_amount);

        $service = app(PaymentService::class);
        $service->applyOxaPayData($payment->fresh(), ['status' => 'Paid']);
        $service->applyOxaPayData($payment->fresh(), ['status' => 'paid']);
        $service->applyOxaPayData($payment->fresh(), ['status' => 'Paying']); // late callback

        $merchant->refresh();
        $this->assertSame('9.40', $merchant->test_balance);
        $this->assertSame('0.00', $merchant->live_balance);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertEquals(
            [['payment', '10.00', '10.00'], ['payment_fee', '-0.60', '9.40']],
            $merchant->ledgerEntries()->orderBy('id')->get()->map(fn ($e) => [$e->type, $e->amount, $e->balance_after])->all()
        );
    }

    public function test_the_database_refuses_a_second_credit_for_the_same_payment(): void
    {
        $merchant = Merchant::factory()->create();
        $payment = Payment::create([
            'public_id' => Payment::newPublicId(), 'merchant_id' => $merchant->id, 'mode' => 'test',
            'amount' => '10.00', 'fee' => '0.60', 'net_amount' => '9.40',
        ]);

        app(Ledger::class)->creditPayment($payment);

        $this->expectException(UniqueConstraintViolationException::class);
        app(Ledger::class)->creditPayment($payment);
    }

    public function test_admin_adjustments_are_recorded(): void
    {
        $admin = $this->admin();
        $merchant = Merchant::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.merchants.adjust', $merchant), ['mode' => 'live', 'direction' => 'credit', 'amount' => '12.34', 'note' => 'Settled underpaid payment'])
            ->assertSessionHas('status');

        $this->post(route('admin.merchants.adjust', $merchant), ['mode' => 'live', 'direction' => 'debit', 'amount' => '50', 'note' => 'Too much'])
            ->assertSessionHasErrors('amount');

        $entry = $merchant->ledgerEntries()->sole();
        $this->assertSame('adjustment', $entry->type);
        $this->assertSame('12.34', $entry->amount);
        $this->assertSame($admin->id, $entry->created_by);
        $this->assertSame('12.34', $merchant->fresh()->live_balance);
    }
}
