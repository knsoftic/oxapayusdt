<?php

namespace App\Http\Controllers\Merchant;

use App\Models\LedgerEntry;
use Illuminate\View\View;

/**
 * Every credit and deduction on the balance: payments, fees, withdrawals.
 */
class BalanceController extends MerchantController
{
    public function index(): View
    {
        $merchant = $this->merchant();
        $mode = $this->mode();

        $entries = LedgerEntry::where('merchant_id', $merchant->id)
            ->where('mode', $mode)
            ->with(['payment:id,public_id', 'payout:id,public_id'])
            ->latest('id')
            ->paginate(25);

        $totals = LedgerEntry::where('merchant_id', $merchant->id)
            ->where('mode', $mode)
            ->selectRaw("SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) AS received")
            ->selectRaw("SUM(CASE WHEN type IN ('payment_fee', 'payout_fee', 'payout_fee_reversal') THEN amount ELSE 0 END) AS fees")
            ->selectRaw("SUM(CASE WHEN type IN ('payout', 'payout_reversal') THEN amount ELSE 0 END) AS withdrawn")
            ->first();

        return view('merchant.balance', [
            'entries' => $entries,
            'balance' => $merchant->balance($mode),
            'received' => (float) $totals->received,
            'fees' => abs((float) $totals->fees),
            'withdrawn' => abs((float) $totals->withdrawn),
        ]);
    }
}
