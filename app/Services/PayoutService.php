<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\Payout;
use App\Models\User;
use App\Services\OxaPay\OxaPayClient;
use App\Services\OxaPay\OxaPayException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PayoutService
{
    public function __construct(
        protected OxaPayClient $oxapay,
        protected Ledger $ledger,
        protected WebhookDispatcher $webhooks,
    ) {}

    /**
     * A merchant asks to withdraw. The amount (including the payout fee) leaves
     * the balance immediately and comes back if the payout is rejected or fails.
     * Test-mode payouts complete straight away; live ones wait for an admin.
     *
     * @param  array{amount: mixed, network: string, address: string, memo?: ?string, idempotency_key?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function request(Merchant $merchant, string $mode, array $data): Payout
    {
        $amount = Money::cents($data['amount']);
        $fee = Money::cents($merchant->payoutFee());
        $minimum = Money::cents(config('platform.min_payout'));

        if ($amount < $minimum) {
            throw ValidationException::withMessages(['amount' => 'The minimum withdrawal is '.Money::usd($minimum / 100).'.']);
        }

        if ($amount <= $fee) {
            throw ValidationException::withMessages(['amount' => 'The amount must be more than the '.Money::usd($fee / 100).' payout fee.']);
        }

        $payout = DB::transaction(function () use ($merchant, $mode, $data, $amount, $fee) {
            $locked = Merchant::whereKey($merchant->id)->lockForUpdate()->firstOrFail();

            if (Money::cents($locked->balance($mode)) < $amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient balance. Available: '.Money::usd($locked->balance($mode)).'.',
                ]);
            }

            $payout = Payout::create([
                'public_id' => Payout::newPublicId(),
                'merchant_id' => $merchant->id,
                'mode' => $mode,
                'status' => Payout::STATUS_PENDING,
                'amount' => Money::format($amount),
                'fee' => Money::format($fee),
                'net_amount' => Money::format($amount - $fee),
                'currency' => config('platform.payout_currency'),
                'network' => $data['network'],
                'address' => trim($data['address']),
                'memo' => filled($data['memo'] ?? null) ? trim($data['memo']) : null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            $this->ledger->debitPayout($payout);

            return $payout;
        });

        $merchant->refresh();

        if ($mode === 'test') {
            // Nothing is sent in test mode; simulate a successful transfer.
            $this->complete($payout, 'test_'.Str::lower(Str::random(40)));
        }

        return $payout->refresh();
    }

    /**
     * Admin approves a live payout: send the USDT through OxaPay.
     *
     * @throws OxaPayException
     */
    public function approve(Payout $payout, User $admin): Payout
    {
        // Claim the payout first so two clicks (or two admins) can never send it twice.
        $claimed = Payout::whereKey($payout->id)
            ->where('status', Payout::STATUS_PENDING)
            ->update([
                'status' => Payout::STATUS_PROCESSING,
                'processed_by' => $admin->id,
                'processed_at' => now(),
                'updated_at' => now(),
            ]);

        if (! $claimed) {
            throw ValidationException::withMessages(['payout' => 'This payout has already been processed.']);
        }

        $payout->refresh();

        try {
            $result = $this->oxapay->createPayout([
                'address' => $payout->address,
                'currency' => $payout->currency,
                'amount' => (float) $payout->net_amount,
                'network' => $payout->network,
                'memo' => $payout->memo,
                'callback_url' => $this->callbackUrl(),
                'description' => "Payout {$payout->public_id} for {$payout->merchant->business_name}",
            ]);
        } catch (OxaPayException $e) {
            if ($e->getCode() === 0) {
                // No answer from OxaPay: the transfer may or may not exist, so don't allow a blind retry.
                $payout->update(['note' => 'No response from OxaPay ('.$e->getMessage().'). Check the OxaPay dashboard, then mark this payout as paid or reject it.']);
            } else {
                // OxaPay refused it, so nothing was sent: back to the approval queue.
                $payout->update(['status' => Payout::STATUS_PENDING, 'note' => 'OxaPay refused the payout: '.$e->getMessage()]);
            }

            throw $e;
        }

        $payout->update([
            'track_id' => isset($result['track_id']) ? (string) $result['track_id'] : null,
            'note' => null,
            'payload' => $result,
        ]);

        // OxaPay may finish internal transfers immediately.
        $this->applyOxaPayStatus($payout, $result);

        return $payout->refresh();
    }

    /**
     * Admin paid the merchant outside OxaPay and records the transaction.
     */
    public function markPaid(Payout $payout, User $admin, ?string $txHash): Payout
    {
        $this->ensurePending($payout, allowProcessing: true);

        $payout->update(['processed_by' => $admin->id, 'processed_at' => now()]);
        $this->complete($payout, $txHash);

        return $payout->refresh();
    }

    /**
     * Admin declines: the full amount goes back to the merchant's balance.
     */
    public function reject(Payout $payout, User $admin, string $reason): Payout
    {
        // "processing" is allowed for transfers OxaPay never confirmed; the admin checks first.
        $this->ensurePending($payout, allowProcessing: true);

        $payout->update(['processed_by' => $admin->id, 'processed_at' => now()]);
        $this->fail($payout, Payout::STATUS_REJECTED, $reason);

        return $payout->refresh();
    }

    /**
     * Apply a payout status from OxaPay (webhook or payout-info response).
     */
    public function applyOxaPayStatus(Payout $payout, array $data): void
    {
        if ($payout->isFinal()) {
            return;
        }

        $status = strtolower((string) ($data['status'] ?? ''));
        $txHash = ($data['tx_hash'] ?? '') ?: null;

        match (true) {
            in_array($status, ['confirmed', 'complete', 'completed'], true) => $this->complete($payout, $txHash),
            in_array($status, ['failed', 'rejected', 'canceled', 'cancelled'], true) => $this->fail($payout, Payout::STATUS_FAILED, 'The transfer was '.$status.' by OxaPay.'),
            default => $txHash ? $payout->update(['tx_hash' => $txHash]) : null,
        };
    }

    /**
     * Refresh a processing payout from OxaPay.
     */
    public function sync(Payout $payout): void
    {
        if ($payout->status !== Payout::STATUS_PROCESSING || ! $payout->track_id) {
            return;
        }

        try {
            $this->applyOxaPayStatus($payout, $this->oxapay->payoutInfo($payout->track_id));
        } catch (OxaPayException) {
            // Try again on the next sync.
        }
    }

    protected function complete(Payout $payout, ?string $txHash): void
    {
        $updated = Payout::whereKey($payout->id)
            ->whereNotIn('status', Payout::FINAL_STATUSES)
            ->update(['status' => Payout::STATUS_COMPLETED, 'tx_hash' => $txHash ?: $payout->tx_hash, 'completed_at' => now(), 'updated_at' => now()]);

        if ($updated) {
            $this->webhooks->payoutEvent($payout->refresh(), 'payout.completed');
        }
    }

    protected function fail(Payout $payout, string $status, string $reason): void
    {
        $failed = DB::transaction(function () use ($payout, $status, $reason) {
            $updated = Payout::whereKey($payout->id)
                ->whereNotIn('status', Payout::FINAL_STATUSES)
                ->update(['status' => $status, 'note' => $reason, 'updated_at' => now()]);

            if ($updated) {
                $this->ledger->reversePayout($payout);
            }

            return (bool) $updated;
        });

        if ($failed) {
            $this->webhooks->payoutEvent($payout->refresh(), 'payout.failed');
        }
    }

    protected function ensurePending(Payout $payout, bool $allowProcessing = false): void
    {
        $allowed = $allowProcessing ? [Payout::STATUS_PENDING, Payout::STATUS_PROCESSING] : [Payout::STATUS_PENDING];

        if (! in_array($payout->status, $allowed, true)) {
            throw ValidationException::withMessages(['payout' => "This payout is already {$payout->status}."]);
        }
    }

    protected function callbackUrl(): ?string
    {
        $url = config('oxapay.callback_url') ?: route('oxapay.webhook');

        return str_starts_with($url, 'https://') ? $url : null;
    }
}
