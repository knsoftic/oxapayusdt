<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Payout;
use Illuminate\View\View;

/**
 * Live-mode overview of the whole platform.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $paidLive = fn () => Payment::where('mode', 'live')->whereIn('status', Payment::PAID_STATUSES);

        // Fees are ledger debits on merchants; returned payout fees are subtracted again.
        $fees = LedgerEntry::where('mode', 'live')
            ->whereIn('type', ['payment_fee', 'payout_fee', 'payout_fee_reversal'])
            ->sum('amount');

        return view('admin.dashboard', [
            'volume' => $paidLive()->sum('amount'),
            'volume30' => $paidLive()->where('paid_at', '>=', now()->subDays(30))->sum('amount'),
            'feesEarned' => abs((float) $fees),
            'owed' => Merchant::sum('live_balance'),
            'merchantCount' => Merchant::count(),
            'liveCount' => Merchant::where('live_enabled', true)->count(),
            'liveRequests' => Merchant::where('live_enabled', false)->whereNotNull('live_requested_at')->with('user')->get(),
            'pendingPayouts' => Payout::where('mode', 'live')->where('status', Payout::STATUS_PENDING)->with('merchant')->oldest('id')->limit(5)->get(),
            'pendingPayoutTotal' => Payout::where('mode', 'live')->where('status', Payout::STATUS_PENDING)->sum('net_amount'),
            'recentPayments' => Payment::where('mode', 'live')->with('merchant')->latest('id')->limit(8)->get(),
            'oxapayConnected' => filled(config('oxapay.merchant_api_key')),
            'payoutKeySet' => filled(config('oxapay.payout_api_key')),
        ]);
    }
}
