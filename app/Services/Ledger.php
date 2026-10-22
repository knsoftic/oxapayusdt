<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Payout;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Every change to a merchant balance goes through here, as ledger entries
 * written under a row lock on the merchant, so balances can't race.
 */
class Ledger
{
    /**
     * @param  list<array{0: string, 1: int, 2: string}>  $entries  [type, signed amount in cents, description]
     * @param  array<string, mixed>  $refs  payment_id / payout_id / created_by
     */
    public function post(Merchant $merchant, string $mode, array $entries, array $refs = []): void
    {
        DB::transaction(function () use ($merchant, $mode, $entries, $refs) {
            $locked = Merchant::whereKey($merchant->id)->lockForUpdate()->firstOrFail();
            $column = $mode === 'live' ? 'live_balance' : 'test_balance';
            $balance = Money::cents($locked->{$column});

            foreach ($entries as [$type, $cents, $description]) {
                if ($cents === 0) {
                    continue;
                }

                $balance += $cents;

                LedgerEntry::create($refs + [
                    'merchant_id' => $locked->id,
                    'mode' => $mode,
                    'type' => $type,
                    'amount' => Money::format($cents),
                    'balance_after' => Money::format($balance),
                    'description' => $description,
                ]);
            }

            $locked->forceFill([$column => Money::format($balance)])->save();
            $merchant->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Credit a paid payment, minus its pay-in fee.
     */
    public function creditPayment(Payment $payment): void
    {
        $this->post($payment->merchant, $payment->mode, [
            ['payment', Money::cents($payment->amount), "Payment {$payment->public_id}"],
            ['payment_fee', -Money::cents($payment->fee), "Pay-in fee for {$payment->public_id}"],
        ], ['payment_id' => $payment->id]);
    }

    /**
     * Take a withdrawal (net amount + payout fee) out of the balance.
     */
    public function debitPayout(Payout $payout): void
    {
        $this->post($payout->merchant, $payout->mode, [
            ['payout', -Money::cents($payout->net_amount), "Withdrawal {$payout->public_id}"],
            ['payout_fee', -Money::cents($payout->fee), "Payout fee for {$payout->public_id}"],
        ], ['payout_id' => $payout->id]);
    }

    /**
     * Give a rejected or failed withdrawal back.
     */
    public function reversePayout(Payout $payout): void
    {
        $this->post($payout->merchant, $payout->mode, [
            ['payout_reversal', Money::cents($payout->net_amount), "Withdrawal {$payout->public_id} returned"],
            ['payout_fee_reversal', Money::cents($payout->fee), "Payout fee for {$payout->public_id} returned"],
        ], ['payout_id' => $payout->id]);
    }

    /**
     * Manual credit (positive) or debit (negative) by an admin.
     */
    public function adjust(Merchant $merchant, string $mode, int $cents, string $note, int $adminId): void
    {
        $this->post($merchant, $mode, [['adjustment', $cents, $note]], ['created_by' => $adminId]);
    }
}
