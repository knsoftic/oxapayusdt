<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Merchant;
use App\Models\PaymentLink;
use App\Models\Setting;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_merchant_page_renders(): void
    {
        $merchant = Merchant::factory()->create(['business_name' => 'Ali Electronics']);
        $payment = app(PaymentService::class)->create($merchant, 'test', ['amount' => '10', 'reference' => 'order_1']);
        app(PaymentService::class)->applyOxaPayData($payment, ['status' => 'paid']);

        $this->actingAs($merchant->user);

        $this->get('/dashboard')->assertOk()->assertSee('$9.40')->assertSee('Get started');
        $this->get('/payments')->assertOk()->assertSee('order_1');
        $this->get('/payments?status=paid&q=order_1')->assertOk()->assertSee('order_1');
        $this->get(route('payments.show', $payment))->assertOk()->assertSee('Pay-in fee')->assertSee('-$0.60');
        $this->get('/links')->assertOk();
        $this->get('/balance')->assertOk()->assertSee('Payment received');
        $this->get('/payouts')->assertOk()->assertSee('$9.40');
        $this->get('/developers')->assertOk();
        $this->get('/settings')->assertOk()->assertSee('1% + $0.50');
    }

    public function test_merchants_cannot_see_each_others_payments(): void
    {
        $mine = Merchant::factory()->create();
        $theirs = Merchant::factory()->create();
        $payment = app(PaymentService::class)->create($theirs, 'test', ['amount' => '10']);

        $this->actingAs($mine->user)->get(route('payments.show', $payment))->assertNotFound();
    }

    public function test_api_keys_are_shown_once_and_stored_hashed(): void
    {
        $merchant = Merchant::factory()->create();

        $response = $this->actingAs($merchant->user)->post(route('developers.keys.roll'), ['mode' => 'test']);
        $plain = session('new_key.key');

        $response->assertSessionHas('new_key');
        $this->assertStringStartsWith('sk_test_', $plain);
        $this->assertSame(hash('sha256', $plain), DB::table('api_keys')->value('key_hash'));
        $this->get('/developers')->assertSee($plain)->assertSee("won't be shown again", false);
        $this->get('/developers')->assertDontSee($plain);

        $this->post(route('developers.keys.roll'), ['mode' => 'live'])->assertSessionHasErrors('key');
        $this->assertSame(1, ApiKey::count());
    }

    public function test_merchant_can_create_and_toggle_payment_links_and_switch_mode(): void
    {
        $merchant = Merchant::factory()->create();
        $this->actingAs($merchant->user);

        $this->post(route('links.store'), ['title' => 'Consulting hour', 'amount' => '75'])->assertSessionHas('status');
        $link = PaymentLink::sole();
        $this->assertSame(['test', '75.00', true], [$link->mode, $link->amount, $link->active]);

        $this->patch(route('links.toggle', $link))->assertSessionHas('status');
        $this->assertFalse($link->fresh()->active);

        $this->post(route('mode.switch'), ['mode' => 'live']);
        $this->get('/links')->assertOk()->assertDontSee('Consulting hour')->assertSee("Live mode isn't activated yet", false);
    }

    public function test_merchant_settings_update_branding_and_request_live(): void
    {
        $merchant = Merchant::factory()->create();
        $this->actingAs($merchant->user);

        $this->put(route('settings.business'), ['business_name' => 'New Name', 'brand_color' => '#123456', 'website' => 'https://new.test'])->assertSessionHas('status');
        $this->post(route('settings.live'))->assertSessionHas('status');

        $merchant->refresh();
        $this->assertSame(['New Name', '#123456'], [$merchant->business_name, $merchant->brand_color]);
        $this->assertNotNull($merchant->live_requested_at);

        $this->put(route('settings.password'), ['current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertSessionHasErrors('current_password');
        $this->put(route('settings.password'), ['current_password' => 'password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertSessionHas('status');
    }

    public function test_every_admin_page_renders_and_admin_manages_merchants(): void
    {
        $merchant = Merchant::factory()->create(['business_name' => 'Ali Electronics', 'live_requested_at' => now()]);
        $this->actingAs($this->admin());

        $this->get('/admin')->assertOk()->assertSee('Ali Electronics');
        $this->get('/admin/merchants')->assertOk()->assertSee('Ali Electronics');
        $this->get('/admin/merchants?filter=requests&q=Ali')->assertOk()->assertSee('Ali Electronics');
        $this->get(route('admin.merchants.show', $merchant))->assertOk();
        $this->get('/admin/payments')->assertOk();
        $this->get('/admin/payments?mode=test&status=paid')->assertOk();
        $this->get('/admin/payouts')->assertOk();
        $this->get('/admin/payouts?status=all')->assertOk();
        $this->get('/admin/settings')->assertOk();

        $this->post(route('admin.merchants.live', $merchant))->assertSessionHas('status');
        $this->put(route('admin.merchants.fees', $merchant), ['fee_payin_percent' => '2', 'fee_payin_fixed' => '', 'fee_payout_fixed' => '0.5']);
        $this->post(route('admin.merchants.status', $merchant));

        $merchant->refresh();
        $this->assertTrue($merchant->live_enabled);
        $this->assertSame(['2.00', null, '0.50'], [$merchant->fee_payin_percent, $merchant->fee_payin_fixed, $merchant->fee_payout_fixed]);
        $this->assertSame(Merchant::STATUS_SUSPENDED, $merchant->status);
    }

    public function test_admin_settings_save_keys_encrypted_and_fees(): void
    {
        $this->actingAs($this->admin());

        $this->put('/admin/settings', [
            'merchant_api_key' => 'MERCHANT-KEY-1234567890-WXYZ',
            'payout_api_key' => '',
            'checkout' => 'hosted',
            'platform_name' => 'QadriPay',
            'brand_color' => '#0EA5E9',
            'payin_percent' => '1.5',
            'payin_fixed' => '0.3',
            'payout_fixed' => '2',
            'min_payment' => '2',
            'min_payout' => '10',
            'auto_convert_usdt' => '1',
        ])->assertSessionHas('status');

        $raw = DB::table('settings')->where('key', 'oxapay.merchant_api_key')->value('value');
        $this->assertStringNotContainsString('MERCHANT-KEY', $raw);

        Setting::applyToConfig();
        $this->assertSame('MERCHANT-KEY-1234567890-WXYZ', config('oxapay.merchant_api_key'));
        $this->assertSame('test-payout-key', config('oxapay.payout_api_key')); // blank keeps the existing key
        // Numbers round-trip through JSON, so 2.0 may come back as 2: compare loosely.
        $this->assertEquals([1.5, 0.3, 2, 'hosted', 'QadriPay', '#0ea5e9'], [
            config('platform.fees.payin_percent'), config('platform.fees.payin_fixed'), config('platform.fees.payout_fixed'),
            config('oxapay.checkout'), config('platform.name'), config('platform.brand_color'),
        ]);

        $this->get('/admin/settings')->assertSee('••••••••WXYZ')->assertDontSee('MERCHANT-KEY-1234567890-WXYZ');
    }

    public function test_admin_connection_test_checks_both_keys(): void
    {
        Http::fake([
            'api.oxapay.com/v1/payment/accepted-currencies' => Http::response($this->oxapay(['list' => ['BTC', 'USDT']])),
            'api.oxapay.com/v1/payout*' => Http::response($this->oxapay(['list' => [], 'meta' => []])),
        ]);

        $this->actingAs($this->admin())->post('/admin/settings/test')
            ->assertSessionHas('test_ok', 'Merchant key works. Accepted currencies: BTC, USDT. Payout key works.');
    }

    public function test_docs_and_postman_collection_are_public(): void
    {
        $this->get('/docs')->assertOk()->assertSee('Create a payment')->assertSee('X-Webhook-Signature')->assertSee(url('/api/v1'));
        $this->assertFileExists(public_path('downloads/postman_collection.json'));
        $this->assertIsArray(json_decode(file_get_contents(public_path('downloads/postman_collection.json')), true));
    }
}
