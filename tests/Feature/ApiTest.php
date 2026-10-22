<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Merchant;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_without_a_valid_key_are_refused(): void
    {
        $this->getJson('/api/v1/payments')->assertStatus(401)->assertJsonPath('error.type', 'authentication_error');
        $this->withToken('sk_test_nope')->getJson('/api/v1/payments')->assertStatus(401);

        [$merchant, $key] = $this->merchantWithKey();
        $newKey = ApiKey::roll($merchant, 'test');

        $this->withToken($key)->getJson('/api/v1/payments')->assertStatus(401); // rolled, so revoked
        $this->withToken($newKey)->getJson('/api/v1/payments')->assertOk();
        $this->withHeader('X-API-Key', $newKey)->getJson('/api/v1/balance')->assertOk();
    }

    public function test_live_keys_need_live_activation_and_suspension_blocks_everything(): void
    {
        [$merchant, $key] = $this->merchantWithKey('live');
        $merchant->update(['live_enabled' => false]);

        $this->withToken($key)->getJson('/api/v1/balance')->assertStatus(403)->assertJsonPath('error.type', 'live_mode_disabled');

        $merchant->update(['live_enabled' => true, 'status' => Merchant::STATUS_SUSPENDED]);
        $this->withToken($key)->getJson('/api/v1/balance')->assertStatus(403)->assertJsonPath('error.type', 'account_suspended');
    }

    public function test_create_payment_returns_the_fee_net_amount_and_checkout_url(): void
    {
        [$merchant, $key] = $this->merchantWithKey();

        $response = $this->withToken($key)->postJson('/api/v1/payments', [
            'amount' => '25',
            'reference' => 'order_1001',
            'description' => '2x T-shirt',
            'customer_email' => 'buyer@example.com',
            'return_url' => 'https://shop.test/thanks',
            'metadata' => ['cart' => 'c_42'],
        ]);

        $payment = Payment::sole();

        $response->assertCreated()->assertExactJson([
            'id' => $payment->public_id,
            'object' => 'payment',
            'mode' => 'test',
            'status' => 'pending',
            'amount' => '25.00',
            'currency' => 'USD',
            'fee' => '0.75',
            'net_amount' => '24.25',
            'reference' => 'order_1001',
            'description' => '2x T-shirt',
            'customer_email' => 'buyer@example.com',
            'checkout_url' => route('checkout.show', $payment),
            'pay_currency' => null,
            'pay_amount' => null,
            'network' => null,
            'metadata' => ['cart' => 'c_42'],
            'expires_at' => $payment->expires_at->toIso8601String(),
            'paid_at' => null,
            'created_at' => $payment->created_at->toIso8601String(),
        ]);

        $this->assertStringStartsWith('pay_', $payment->public_id);
        $this->assertSame($merchant->id, $payment->merchant_id);
        Http::assertNothingSent(); // OxaPay is only called when the customer starts paying
    }

    public function test_validation_errors_use_the_api_error_format(): void
    {
        [, $key] = $this->merchantWithKey();

        $this->withToken($key)->postJson('/api/v1/payments', ['amount' => '0', 'currency' => 'EUR', 'customer_email' => 'nope'])
            ->assertStatus(422)
            ->assertJsonPath('error.type', 'invalid_request')
            ->assertJsonStructure(['error' => ['message', 'fields' => ['amount', 'currency', 'customer_email']]]);

        // Without an Accept header (e.g. plain cURL) the API still answers in JSON.
        $this->withToken($key)->post('/api/v1/payments', [])->assertStatus(422)->assertJsonPath('error.type', 'invalid_request');
    }

    public function test_idempotency_key_returns_the_same_payment(): void
    {
        [, $key] = $this->merchantWithKey();

        $first = $this->withToken($key)->withHeader('Idempotency-Key', 'order_7')->postJson('/api/v1/payments', ['amount' => '10'])->assertCreated();
        $second = $this->withToken($key)->withHeader('Idempotency-Key', 'order_7')->postJson('/api/v1/payments', ['amount' => '10'])->assertOk();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_merchants_only_see_their_own_payments_in_the_keys_mode(): void
    {
        [$merchant, $testKey] = $this->merchantWithKey();
        [$other, $otherKey] = $this->merchantWithKey();
        $service = app(PaymentService::class);

        $mine = $service->create($merchant, 'test', ['amount' => '10', 'reference' => 'A']);
        $service->create($merchant, 'test', ['amount' => '11', 'reference' => 'B']);
        $live = $service->create($merchant, 'live', ['amount' => '12']);
        $theirs = $service->create($other, 'test', ['amount' => '13']);

        $this->withToken($testKey)->getJson('/api/v1/payments')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
        $this->withToken($testKey)->getJson('/api/v1/payments?reference=A')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->public_id);
        $this->withToken($testKey)->getJson("/api/v1/payments/{$mine->public_id}")->assertOk()->assertJsonPath('amount', '10.00');
        $this->withToken($testKey)->getJson("/api/v1/payments/{$live->public_id}")->assertNotFound()->assertJsonPath('error.type', 'not_found');
        $this->withToken($testKey)->getJson("/api/v1/payments/{$theirs->public_id}")->assertNotFound();
        $this->withToken($otherKey)->getJson("/api/v1/payments/{$mine->public_id}")->assertNotFound();
    }

    public function test_retrieving_an_open_payment_refreshes_it_from_oxapay(): void
    {
        [$merchant, $key] = $this->merchantWithKey();
        $payment = app(PaymentService::class)->create($merchant, 'test', ['amount' => '10']);
        $payment->update(['track_id' => '555', 'type' => 'invoice', 'status' => 'new']);

        Http::fake(['api.oxapay.com/v1/payment/555' => Http::response($this->oxapay(['track_id' => '555', 'status' => 'paid', 'txs' => []]))]);

        $this->withToken($key)->getJson("/api/v1/payments/{$payment->public_id}")->assertOk()->assertJsonPath('status', 'paid');
        $this->withToken($key)->getJson('/api/v1/balance')->assertOk()->assertJson([
            'object' => 'balance',
            'mode' => 'test',
            'currency' => 'USD',
            'available' => '9.40',
            'pending_payouts' => '0.00',
        ]);
    }

    public function test_test_mode_payouts_complete_instantly_and_debit_the_balance(): void
    {
        [$merchant, $key] = $this->merchantWithKey();
        $merchant->forceFill(['test_balance' => '20.00'])->save();

        $this->withToken($key)->postJson('/api/v1/payouts', [
            'amount' => '30', 'network' => 'Tron', 'address' => 'TQ7m2wYourTronAddressHere123456789',
        ])->assertStatus(422)->assertJsonPath('error.fields.amount.0', 'Insufficient balance. Available: $20.00.');

        $this->withToken($key)->postJson('/api/v1/payouts', [
            'amount' => '10', 'network' => 'Tron', 'address' => '0x0000000000000000000000000000000000000000',
        ])->assertStatus(422)->assertJsonPath('error.fields.address.0', "This doesn't look like a valid Tron address.");

        $response = $this->withToken($key)->postJson('/api/v1/payouts', [
            'amount' => '10', 'network' => 'Tron', 'address' => 'TQ7m2wYourTronAddressHere123456789',
        ])->assertCreated()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('fee', '1.00')
            ->assertJsonPath('net_amount', '9.00')
            ->assertJsonPath('currency', 'USDT');

        $this->assertStringStartsWith('test_', $response->json('tx_hash'));
        $this->assertSame('10.00', $merchant->fresh()->test_balance);
        $this->withToken($key)->getJson('/api/v1/payouts')->assertJsonCount(1, 'data');
        $this->withToken($key)->getJson('/api/v1/payouts/'.$response->json('id'))->assertOk();
        Http::assertNothingSent();
    }

    public function test_live_payouts_wait_for_admin_review(): void
    {
        [$merchant, $key] = $this->merchantWithKey('live');
        $merchant->forceFill(['live_balance' => '100.00'])->save();

        $this->withToken($key)->withHeader('Idempotency-Key', 'wd-1')->postJson('/api/v1/payouts', [
            'amount' => '50', 'network' => 'BSC', 'address' => '0x52908400098527886E0F7030069857D2E4169EE7',
        ])->assertCreated()->assertJsonPath('status', 'pending');

        $this->withToken($key)->withHeader('Idempotency-Key', 'wd-1')->postJson('/api/v1/payouts', [
            'amount' => '50', 'network' => 'BSC', 'address' => '0x52908400098527886E0F7030069857D2E4169EE7',
        ])->assertOk();

        $this->assertDatabaseCount('payouts', 1);
        $this->assertSame('50.00', $merchant->fresh()->live_balance);
        $this->withToken($key)->getJson('/api/v1/balance')->assertJsonPath('pending_payouts', '50.00');
    }
}
