<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\Payment;
use App\Services\OxaPay\OxaPayClient;
use App\Services\OxaPay\OxaPayException;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        protected OxaPayClient $oxapay,
        protected Ledger $ledger,
        protected WebhookDispatcher $webhooks,
    ) {}

    /**
     * Create a payment for a merchant. Nothing is sent to OxaPay until the
     * customer opens the checkout and chooses how to pay.
     *
     * @param  array{amount: mixed, reference?: ?string, description?: ?string, customer_email?: ?string, callback_url?: ?string, return_url?: ?string, metadata?: ?array, idempotency_key?: ?string, payment_link_id?: ?int}  $data
     */
    public function create(Merchant $merchant, string $mode, array $data): Payment
    {
        $amount = Money::format(Money::cents($data['amount']));
        $fee = $merchant->payinFeeFor($amount);

        return Payment::create([
            'public_id' => Payment::newPublicId(),
            'merchant_id' => $merchant->id,
            'payment_link_id' => $data['payment_link_id'] ?? null,
            'mode' => $mode,
            'status' => 'pending',
            'amount' => $amount,
            'currency' => 'USD',
            'fee' => $fee,
            'net_amount' => Money::format(Money::cents($amount) - Money::cents($fee)),
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'callback_url' => $data['callback_url'] ?? null,
            'return_url' => $data['return_url'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'expires_at' => now()->addMinutes(config('oxapay.lifetime')),
        ]);
    }

    /**
     * Whether this payment is paid on OxaPay's hosted page (test mode always,
     * live mode when the admin chose "hosted") instead of our coin picker.
     */
    public function usesHostedPage(Payment $payment): bool
    {
        return $payment->isTest() || config('oxapay.checkout') === 'hosted';
    }

    /**
     * Create the OxaPay side of a payment once the customer is ready to pay.
     *
     * @throws OxaPayException
     */
    public function start(Payment $payment, ?string $payCurrency = null, ?string $network = null): Payment
    {
        $hosted = $this->usesHostedPage($payment);
        $merchant = $payment->merchant;

        $params = [
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'order_id' => $payment->public_id,
            'email' => $payment->customer_email,
            'description' => Str::limit($merchant->business_name.($payment->description ? ': '.$payment->description : ''), 250),
            'lifetime' => config('oxapay.lifetime'),
            'fee_paid_by_payer' => config('oxapay.fee_paid_by_payer'),
            'under_paid_coverage' => config('oxapay.under_paid_coverage'),
            'callback_url' => $this->callbackUrl(),
        ];

        // Convert received crypto to USDT so balances owed to merchants keep their value.
        if (! $payment->isTest() && config('platform.auto_convert_usdt') && $payCurrency !== 'USDT') {
            $params['to_currency'] = 'USDT';
        }

        $result = $hosted
            ? $this->oxapay->createInvoice($params + [
                'return_url' => route('checkout.show', $payment),
                'sandbox' => $payment->isTest(),
            ])
            : $this->oxapay->createWhiteLabel($params + [
                'pay_currency' => $payCurrency,
                'network' => $network,
            ]);

        $payment->update([
            'type' => $hosted ? 'invoice' : 'white_label',
            'track_id' => (string) $result['track_id'],
            'status' => $hosted ? 'new' : 'waiting',
            'expires_at' => isset($result['expired_at']) ? Carbon::createFromTimestamp($result['expired_at']) : $payment->expires_at,
            'payment_url' => $result['payment_url'] ?? null,
            'pay_currency' => $result['pay_currency'] ?? null,
            'pay_amount' => isset($result['pay_amount']) ? $this->decimal($result['pay_amount']) : null,
            'network' => $result['network'] ?? null,
            'address' => $result['address'] ?? null,
            'memo' => ($result['memo'] ?? '') ?: null,
            'qr_code' => $result['qr_code'] ?? null,
        ]);

        return $payment;
    }

    /**
     * Bring a payment up to date: expire it if nobody started paying in time,
     * otherwise ask OxaPay for its status.
     */
    public function sync(Payment $payment): void
    {
        if ($payment->isFinal()) {
            return;
        }

        if ($payment->isAwaitingStart()) {
            if ($payment->expires_at?->isPast()) {
                $this->transition($payment, 'expired');
            }

            return;
        }

        try {
            $this->applyOxaPayData($payment, $this->oxapay->paymentInfo($payment->track_id));
        } catch (OxaPayException $e) {
            Log::warning('OxaPay payment lookup failed', ['payment' => $payment->public_id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Apply a status received from OxaPay (webhook payload or payment-info response).
     */
    public function applyOxaPayData(Payment $payment, array $data): void
    {
        // Webhooks send "Paying"/"Paid", the payment-info endpoint sends lowercase.
        $status = strtolower((string) ($data['status'] ?? ''));

        // On OxaPay's hosted page the coin is chosen there; learn it from the first transaction.
        $tx = $data['txs'][0] ?? null;

        if (! $payment->pay_currency && is_array($tx) && ! empty($tx['currency'])) {
            $payment->update([
                'pay_currency' => $tx['currency'],
                'pay_amount' => $this->decimal($tx['amount'] ?? $tx['sent_amount'] ?? 0),
                'network' => $tx['network'] ?? null,
            ]);
        }

        if ($status !== '') {
            $this->transition($payment, $status, $data);
        }
    }

    /**
     * Move a payment to a new status. The first move to "paid" credits the
     * merchant's balance in the same transaction, so it happens exactly once.
     */
    protected function transition(Payment $payment, string $status, array $data = []): void
    {
        $event = DB::transaction(function () use ($payment, $status, $data) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($locked->status === $status) {
                return null;
            }

            // Once paid, only a refund may change the status (e.g. a late "paying" callback is ignored).
            if ($locked->paid_at && ! in_array($status, ['refunding', 'refunded'], true)) {
                return null;
            }

            $locked->fill(['status' => $status, 'payload' => $data ?: $locked->payload]);

            if (in_array($status, Payment::PAID_STATUSES, true)) {
                $locked->paid_at = now();
                $locked->save();
                $this->ledger->creditPayment($locked);
            } else {
                $locked->save();
            }

            return match ($locked->publicStatus()) {
                'confirming' => 'payment.confirming',
                'paid' => 'payment.paid',
                'underpaid' => 'payment.underpaid',
                'expired' => 'payment.expired',
                'refunded' => 'payment.refunded',
                default => null,
            };
        });

        $payment->refresh();

        if ($event) {
            $this->webhooks->paymentEvent($payment, $event);
        }
    }

    /**
     * Plain decimal string for a crypto amount (no exponent, no float noise).
     */
    protected function decimal(int|float|string $value): string
    {
        return rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');
    }

    /**
     * OxaPay only delivers webhooks to public HTTPS URLs, so skip it otherwise.
     */
    protected function callbackUrl(): ?string
    {
        $url = config('oxapay.callback_url') ?: route('oxapay.webhook');

        return str_starts_with($url, 'https://') ? $url : null;
    }
}
