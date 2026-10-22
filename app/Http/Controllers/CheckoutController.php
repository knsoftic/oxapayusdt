<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\CheckoutCurrencies;
use App\Services\OxaPay\OxaPayException;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * The page a merchant's customer pays on (checkout_url in the API).
 */
class CheckoutController extends Controller
{
    public function __construct(protected PaymentService $payments)
    {
        //
    }

    public function show(Payment $payment, CheckoutCurrencies $currencies): View
    {
        $this->payments->sync($payment);
        $merchant = $payment->merchant;

        if ($payment->isAwaitingStart()) {
            $hosted = $this->payments->usesHostedPage($payment);

            return view('checkout.start', [
                'payment' => $payment,
                'merchant' => $merchant,
                'hosted' => $hosted,
                'currencies' => $hosted ? [] : $currencies->all(),
            ]);
        }

        return view('checkout.show', ['payment' => $payment, 'merchant' => $merchant]);
    }

    /**
     * The customer is ready to pay: create the OxaPay side.
     */
    public function start(Request $request, Payment $payment, CheckoutCurrencies $currencies): RedirectResponse
    {
        if (! $payment->isAwaitingStart() || $payment->expires_at?->isPast()) {
            return redirect()->route('checkout.show', $payment);
        }

        $hosted = $this->payments->usesHostedPage($payment);
        [$coin, $network] = $hosted ? [null, null] : $currencies->resolve($request->input('pay_currency'), $request->input('network'));

        try {
            $this->payments->start($payment, $coin, $network);
        } catch (OxaPayException $e) {
            Log::error('OxaPay payment creation failed', ['payment' => $payment->public_id, 'error' => $e->getMessage(), 'response' => $e->response]);

            return back()->withInput()->withErrors(['oxapay' => 'We could not start this payment right now. Please try again in a moment.']);
        }

        return $hosted
            ? redirect()->away($payment->payment_url)
            : redirect()->route('checkout.show', $payment);
    }

    /**
     * Polled by the payment page to notice status changes.
     */
    public function status(Payment $payment): JsonResponse
    {
        $this->payments->sync($payment);

        return response()->json([
            'status' => $payment->status,
            'final' => $payment->isFinal(),
        ]);
    }
}
