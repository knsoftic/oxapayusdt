<?php

namespace Tests;

use App\Models\ApiKey;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Every test talks to fakes only; an unfaked HTTP call fails the test.
        Http::preventStrayRequests();

        config([
            'oxapay.merchant_api_key' => 'test-merchant-key',
            'oxapay.payout_api_key' => 'test-payout-key',
            'oxapay.callback_url' => 'https://gateway.test/oxapay/webhook',
            'oxapay.checkout' => 'custom',
            'platform.fees.payin_percent' => 1.0,
            'platform.fees.payin_fixed' => 0.50,
            'platform.fees.payout_percent' => 0.0,
            'platform.fees.payout_fixed' => 1.00,
            'platform.min_payment' => 1.0,
            'platform.max_payment' => null,
            'platform.min_payout' => 5.0,
            'platform.max_payout' => null,
            'platform.auto_convert_usdt' => true,
        ]);
    }

    /**
     * A merchant with an active API key for the mode.
     *
     * @return array{0: Merchant, 1: string}
     */
    protected function merchantWithKey(string $mode = 'test', array $attributes = []): array
    {
        $merchant = Merchant::factory()->create($attributes + ['live_enabled' => $mode === 'live']);

        return [$merchant, ApiKey::roll($merchant, $mode)];
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * The JSON envelope every OxaPay v1 response uses.
     */
    protected function oxapay(array $data, int $status = 200): array
    {
        return [
            'data' => $data,
            'message' => $status === 200 ? 'Operation completed successfully!' : 'Error',
            'error' => $status === 200 ? [] : ['type' => 'error', 'key' => 'error', 'message' => $data['message'] ?? 'Error'],
            'status' => $status,
            'version' => '1.0.0',
        ];
    }

    protected function signedOxaPayWebhook(array $payload, string $key = 'test-merchant-key')
    {
        $body = json_encode($payload);

        return $this->call('POST', '/oxapay/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_HMAC' => hash_hmac('sha512', $body, $key),
        ], $body);
    }
}
