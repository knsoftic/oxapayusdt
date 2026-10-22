<?php

namespace App\Services\OxaPay;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the OxaPay v1 API (https://docs.oxapay.com).
 */
class OxaPayClient
{
    public function __construct(
        protected ?string $merchantApiKey,
        protected ?string $payoutApiKey = null,
        protected string $baseUrl = 'https://api.oxapay.com/v1',
        protected int $timeout = 30,
    ) {}

    /**
     * Create a hosted payment page. Pass "sandbox" => true for a test invoice.
     * Returns track_id, payment_url, expired_at, date.
     */
    public function createInvoice(array $params): array
    {
        return $this->post('payment/invoice', $params);
    }

    /**
     * Create a white-label payment (we render the address / QR code ourselves).
     * Requires pay_currency and amount. OxaPay has no sandbox for these.
     */
    public function createWhiteLabel(array $params): array
    {
        return $this->post('payment/white-label', $params);
    }

    /**
     * Create a permanent deposit address for a network. Requires network.
     */
    public function createStaticAddress(array $params): array
    {
        return $this->post('payment/static-address', $params);
    }

    /**
     * Fetch the current state of a payment, including its transactions.
     */
    public function paymentInfo(string $trackId): array
    {
        return $this->get('payment/'.rawurlencode($trackId));
    }

    /**
     * List payments. Supports filters such as status, type, from_date, to_date, page and size.
     */
    public function paymentHistory(array $query = []): array
    {
        return $this->get('payment', $query);
    }

    /**
     * Currencies enabled for the merchant account.
     */
    public function acceptedCurrencies(): array
    {
        return $this->get('payment/accepted-currencies')['list'] ?? [];
    }

    /**
     * Every currency OxaPay supports, keyed by symbol, with its networks.
     */
    public function currencies(): array
    {
        return $this->get('common/currencies');
    }

    /**
     * Send crypto from the OxaPay balance. Requires address, currency and amount
     * (in crypto units); network is needed for multi-network currencies.
     * Returns track_id and status.
     */
    public function createPayout(array $params): array
    {
        return $this->post('payout', $params, 'payout');
    }

    public function payoutInfo(string $trackId): array
    {
        return $this->get('payout/'.rawurlencode($trackId), [], 'payout');
    }

    /**
     * List payouts. Supports filters such as status, currency, page and size.
     */
    public function payoutHistory(array $query = []): array
    {
        return $this->get('payout', $query, 'payout');
    }

    public function hasPayoutKey(): bool
    {
        return filled($this->payoutApiKey);
    }

    /**
     * Check the HMAC header OxaPay sends with every webhook.
     *
     * The signature is HMAC-SHA512 of the raw request body, keyed with the
     * merchant API key (or the payout API key for payout callbacks).
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signature, ?string $type = null): bool
    {
        $key = $type === 'payout' ? $this->payoutApiKey : $this->merchantApiKey;

        if (blank($key) || blank($signature)) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawBody, $key), strtolower($signature));
    }

    protected function get(string $uri, array $query = [], string $keyType = 'merchant'): array
    {
        return $this->send($keyType, fn (PendingRequest $http) => $http->get($uri, $query));
    }

    protected function post(string $uri, array $payload, string $keyType = 'merchant'): array
    {
        $payload = array_filter($payload, fn ($value) => $value !== null);

        return $this->send($keyType, fn (PendingRequest $http) => $http->post($uri, $payload));
    }

    /**
     * @param  callable(PendingRequest): Response  $callback
     */
    protected function send(string $keyType, callable $callback): array
    {
        [$header, $key] = $keyType === 'payout'
            ? ['payout_api_key', $this->payoutApiKey]
            : ['merchant_api_key', $this->merchantApiKey];

        if (blank($key)) {
            throw new OxaPayException("OxaPay {$keyType} API key is not configured. Add it in Admin > Settings.");
        }

        $http = Http::baseUrl($this->baseUrl)
            ->withHeaders([$header => $key])
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout);

        try {
            $response = $callback($http);
        } catch (ConnectionException $e) {
            throw new OxaPayException('Could not connect to OxaPay: '.$e->getMessage(), 0);
        }

        $body = $response->json();

        if ($response->failed() || ! is_array($body) || ! empty($body['error'])) {
            $message = $body['error']['message'] ?? $body['message'] ?? 'OxaPay request failed.';

            throw new OxaPayException($message, $response->status(), is_array($body) ? $body : null);
        }

        return $body['data'] ?? [];
    }
}
