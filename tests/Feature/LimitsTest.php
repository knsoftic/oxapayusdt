<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Setting;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_payments_must_fit_the_platform_default_limits(): void
    {
        config(['platform.min_payment' => 2.0, 'platform.max_payment' => 500.0]);
        [, $key] = $this->merchantWithKey();

        $this->withToken($key)->postJson('/api/v1/payments', ['amount' => '1.50'])
            ->assertStatus(422)->assertJsonPath('error.fields.amount.0', 'The minimum payment is $2.00.');

        $this->withToken($key)->postJson('/api/v1/payments', ['amount' => '500.01'])
            ->assertStatus(422)->assertJsonPath('error.fields.amount.0', 'The maximum payment is $500.00.');

        $this->withToken($key)->postJson('/api/v1/payments', ['amount' => '500'])->assertCreated();
        $this->withToken($key)->postJson('/api/v1/payments', ['amount' => '2'])->assertCreated();
        $this->assertSame(2, Payment::count());
    }

    public function test_a_merchant_override_beats_the_default(): void
    {
        config(['platform.min_payment' => 1.0, 'platform.max_payment' => 100.0]);
        [$merchant, $key] = $this->merchantWithKey('test', ['limit_payin_min' => 10, 'limit_payin_max' => 2000]);

        $this->withToken($key)->postJson('/api/v1/payments', ['amount' => '5'])
            ->assertStatus(422)->assertJsonPath('error.fields.amount.0', 'The minimum payment is $10.00.');
        $this->withToken($key)->postJson('/api/v1/payments', ['amount' => '1500'])->assertCreated();

        $this->withToken($key)->getJson('/api/v1/balance')->assertJsonPath('limits', [
            'payment_min' => '10.00', 'payment_max' => '2000.00', 'payout_min' => '5.00', 'payout_max' => null,
        ]);

        $this->assertSame('$10.00 – $2,000.00', $merchant->payinLimitLabel());
        $this->assertSame('$5.00 or more', $merchant->payoutLimitLabel());
    }

    public function test_payment_links_respect_limits(): void
    {
        $merchant = Merchant::factory()->create(['limit_payin_min' => 5, 'limit_payin_max' => 50]);
        $this->actingAs($merchant->user);

        $this->post(route('links.store'), ['title' => 'Too big', 'amount' => '75'])
            ->assertSessionHasErrors(['amount' => 'The maximum payment is $50.00.']);
        $this->assertSame(0, PaymentLink::count());

        $open = PaymentLink::create(['merchant_id' => $merchant->id, 'mode' => 'test', 'title' => 'Tip jar']);
        $this->get(route('links.show', $open))->assertSee('min="5"', false)->assertSee('max="50"', false);
        $this->post(route('links.pay', $open), ['amount' => '3'])->assertSessionHasErrors(['amount' => 'The minimum payment is $5.00.']);
        $this->post(route('links.pay', $open), ['amount' => '20'])->assertRedirect();
        $this->assertSame(1, Payment::count());
    }

    public function test_withdrawals_respect_minimum_and_maximum(): void
    {
        $merchant = Merchant::factory()->create(['limit_payout_min' => 20, 'limit_payout_max' => 100]);
        $merchant->forceFill(['test_balance' => '500.00'])->save();
        $payouts = app(PayoutService::class);
        $request = fn (string $amount) => $payouts->request($merchant, 'test', ['amount' => $amount, 'network' => 'Tron', 'address' => 'TQ7m2wYourTronAddressHere123456789']);

        foreach (['10' => 'The minimum withdrawal is $20.00.', '150' => 'The maximum withdrawal is $100.00.'] as $amount => $message) {
            try {
                $request($amount);
                $this->fail("A {$amount} withdrawal should be refused.");
            } catch (ValidationException $e) {
                $this->assertSame($message, $e->errors()['amount'][0]);
            }
        }

        $this->assertSame('completed', $request('100')->status);
        $this->assertSame('400.00', $merchant->fresh()->test_balance);
    }

    public function test_admin_sets_default_limits_and_per_merchant_limits(): void
    {
        $this->actingAs($this->admin());
        $merchant = Merchant::factory()->create();

        $settings = [
            'checkout' => 'custom', 'platform_name' => 'PayGate', 'brand_color' => '#4f46e5',
            'payin_percent' => '1', 'payin_fixed' => '0.5', 'payout_percent' => '0', 'payout_fixed' => '1',
            'min_payment' => '2', 'max_payment' => '1000', 'min_payout' => '10', 'max_payout' => '',
        ];

        $this->put('/admin/settings', ['max_payment' => '1'] + $settings)->assertSessionHasErrors('max_payment');
        $this->put('/admin/settings', $settings)->assertSessionHas('status');

        Setting::applyToConfig();
        $this->assertEquals([2, 1000, 10, null], [config('platform.min_payment'), config('platform.max_payment'), config('platform.min_payout'), config('platform.max_payout')]);
        $this->assertSame('$2.00 – $1,000.00', $merchant->fresh()->payinLimitLabel());

        // A per-merchant minimum above the default maximum would block every payment.
        $this->put(route('admin.merchants.fees', $merchant), ['limit_payin_min' => '1500'])
            ->assertSessionHasErrors(['limit_payin_max' => 'The maximum payment ($1,000.00) is below the minimum ($1,500.00).']);

        $this->put(route('admin.merchants.fees', $merchant), ['limit_payin_min' => '1500', 'limit_payin_max' => '10000', 'limit_payout_max' => '250'])
            ->assertSessionHas('status');

        $merchant->refresh();
        $this->assertSame('$1,500.00 – $10,000.00', $merchant->payinLimitLabel());
        $this->assertSame('$10.00 – $250.00', $merchant->payoutLimitLabel());

        $this->get(route('admin.merchants.show', $merchant))->assertOk()->assertSee('$1,500.00 – $10,000.00');
        $this->get('/admin/merchants/create')->assertOk()->assertSee('Max withdrawal $');
    }

    public function test_admin_can_create_a_merchant_with_limits(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/merchants', [
            'name' => 'Ali', 'email' => 'ali@example.com', 'password' => 'given-by-admin-1', 'business_name' => 'Ali Electronics',
            'limit_payin_min' => '25', 'limit_payin_max' => '5000', 'limit_payout_min' => '50',
        ])->assertRedirect();

        $merchant = Merchant::sole();
        $this->assertSame(['25.00', '5000.00', '50.00', null], [$merchant->limit_payin_min, $merchant->limit_payin_max, $merchant->limit_payout_min, $merchant->limit_payout_max]);

        $this->actingAs($merchant->user)->get('/settings')->assertOk()->assertSee('$25.00 – $5,000.00')->assertSee('$50.00 or more');
    }
}
