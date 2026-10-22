<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Payout;
use App\Services\OxaPay\OxaPayClient;
use App\Services\PaymentService;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class OxaPayWebhookController extends Controller
{
    /**
     * Receive payment and payout status callbacks from OxaPay.
     *
     * OxaPay retries up to 5 times until it gets HTTP 200 with the body "ok".
     */
    public function __invoke(Request $request, OxaPayClient $oxapay, PaymentService $payments, PayoutService $payouts): Response
    {
        $rawBody = $request->getContent();
        $data = json_decode($rawBody, true);

        if (! is_array($data)) {
            return response('Invalid payload', 400);
        }

        $type = $data['type'] ?? null;

        if (! $oxapay->verifyWebhookSignature($rawBody, $request->header('HMAC'), $type)) {
            Log::warning('OxaPay webhook rejected: invalid HMAC signature', [
                'ip' => $request->ip(),
                'track_id' => $data['track_id'] ?? null,
            ]);

            return response('Invalid HMAC signature', 400);
        }

        $trackId = (string) ($data['track_id'] ?? '');

        if ($type === 'payout') {
            $payout = Payout::where('track_id', $trackId)->first();

            $payout
                ? $payouts->applyOxaPayStatus($payout, $data)
                : Log::warning('OxaPay payout webhook for an unknown payout', ['track_id' => $trackId]);

            return response('ok');
        }

        $payment = Payment::where('track_id', $trackId)->first()
            ?? Payment::where('public_id', (string) ($data['order_id'] ?? ''))->first();

        if (! $payment) {
            Log::warning('OxaPay webhook for an unknown payment', [
                'track_id' => $trackId,
                'order_id' => $data['order_id'] ?? null,
            ]);

            // Acknowledge anyway so OxaPay stops retrying a payment we don't track.
            return response('ok');
        }

        $payments->applyOxaPayData($payment, $data);

        return response('ok');
    }
}
