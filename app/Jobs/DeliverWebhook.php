<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * POST one event to a merchant's webhook URL, retrying with backoff until
 * the endpoint answers with a 2xx status.
 */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * First attempt plus one retry per backoff step in config/platform.php.
     */
    public int $tries = 6;

    public function __construct(public WebhookDelivery $delivery)
    {
        //
    }

    public function handle(): void
    {
        $delivery = $this->delivery;
        $timestamp = time();
        $secret = (string) $delivery->merchant->webhook_secret;

        $code = null;
        $body = null;

        try {
            $response = Http::timeout(config('platform.webhooks.timeout'))
                ->withHeaders([
                    'User-Agent' => Str::slug(config('platform.name')).'-webhooks/1.0',
                    'X-Webhook-Id' => $delivery->event_id,
                    'X-Webhook-Timestamp' => (string) $timestamp,
                    'X-Webhook-Signature' => hash_hmac('sha256', $timestamp.'.'.$delivery->payload, $secret),
                ])
                ->withBody($delivery->payload, 'application/json')
                ->post($delivery->url);

            $code = $response->status();
            $body = Str::limit($response->body(), 1000);
            $succeeded = $response->successful();
        } catch (ConnectionException $e) {
            $body = Str::limit($e->getMessage(), 1000);
            $succeeded = false;
        }

        $backoff = config('platform.webhooks.backoff');
        $willRetry = ! $succeeded && $this->attempts() <= count($backoff);

        $delivery->update([
            'attempts' => $delivery->attempts + 1,
            'response_code' => $code,
            'response_body' => $body,
            'last_attempt_at' => now(),
            'status' => $succeeded ? 'succeeded' : ($willRetry ? 'retrying' : 'failed'),
            'delivered_at' => $succeeded ? now() : null,
        ]);

        if ($willRetry) {
            $this->release($backoff[$this->attempts() - 1]);
        }
    }
}
