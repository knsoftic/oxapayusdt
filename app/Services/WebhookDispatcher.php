<?php

namespace App\Services;

use App\Http\Resources\PaymentResource;
use App\Http\Resources\PayoutResource;
use App\Jobs\DeliverWebhook;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\WebhookDelivery;
use Illuminate\Support\Str;

/**
 * Queues signed event notifications to merchants' webhook URLs.
 */
class WebhookDispatcher
{
    public function paymentEvent(Payment $payment, string $event): ?WebhookDelivery
    {
        $url = $payment->callback_url ?: $payment->merchant->webhook_url;

        return $this->send($payment->merchant, $event, $payment->mode, $url, PaymentResource::make($payment)->resolve(), [
            'payment_id' => $payment->id,
        ]);
    }

    public function payoutEvent(Payout $payout, string $event): ?WebhookDelivery
    {
        return $this->send($payout->merchant, $event, $payout->mode, $payout->merchant->webhook_url, PayoutResource::make($payout)->resolve(), [
            'payout_id' => $payout->id,
        ]);
    }

    /**
     * A "ping" event merchants can trigger from the portal to test their endpoint.
     */
    public function testEvent(Merchant $merchant, string $mode): ?WebhookDelivery
    {
        return $this->send($merchant, 'test.ping', $mode, $merchant->webhook_url, [
            'message' => 'This is a test webhook from '.config('platform.name').'.',
        ]);
    }

    protected function send(Merchant $merchant, string $event, string $mode, ?string $url, array $data, array $refs = []): ?WebhookDelivery
    {
        if (blank($url)) {
            return null;
        }

        $eventId = 'evt_'.Str::lower(Str::ulid());

        $delivery = WebhookDelivery::create($refs + [
            'merchant_id' => $merchant->id,
            'event_id' => $eventId,
            'event' => $event,
            'mode' => $mode,
            'url' => $url,
            'payload' => json_encode([
                'id' => $eventId,
                'type' => $event,
                'mode' => $mode,
                'created_at' => now()->toIso8601String(),
                'data' => $data,
            ], JSON_UNESCAPED_SLASHES),
        ]);

        DeliverWebhook::dispatch($delivery)->afterCommit();

        return $delivery;
    }
}
