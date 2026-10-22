<?php

namespace App\Http\Controllers\Merchant;

use App\Models\ApiKey;
use App\Models\Payment;
use App\Models\Payout;
use Illuminate\View\View;

class DashboardController extends MerchantController
{
    public function __invoke(): View
    {
        $merchant = $this->merchant();
        $mode = $this->mode();
        $paid = fn () => Payment::forMerchant($merchant, $mode)->whereIn('status', Payment::PAID_STATUSES);

        // Paid volume per day for the last 14 days.
        $daily = $paid()->where('paid_at', '>=', today()->subDays(13))
            ->get(['amount', 'paid_at'])
            ->groupBy(fn (Payment $p) => $p->paid_at->toDateString())
            ->map(fn ($payments) => $payments->sum('amount'));

        $chart = collect(range(13, 0))->map(function (int $daysAgo) use ($daily) {
            $date = today()->subDays($daysAgo);

            return ['date' => $date, 'amount' => (float) ($daily[$date->toDateString()] ?? 0)];
        });

        return view('merchant.dashboard', [
            'balance' => $merchant->balance($mode),
            'volumeToday' => $paid()->where('paid_at', '>=', today())->sum('amount'),
            'volume30' => $paid()->where('paid_at', '>=', now()->subDays(30))->sum('amount'),
            'paidCount30' => $paid()->where('paid_at', '>=', now()->subDays(30))->count(),
            'pendingPayouts' => Payout::forMerchant($merchant, $mode)->whereIn('status', [Payout::STATUS_PENDING, Payout::STATUS_PROCESSING])->sum('amount'),
            'recentPayments' => Payment::forMerchant($merchant, $mode)->latest('id')->limit(6)->get(),
            'chart' => $chart,
            'chartMax' => max(1, $chart->max('amount')),
            'hasKey' => ApiKey::where('merchant_id', $merchant->id)->where('mode', $mode)->whereNull('revoked_at')->exists(),
            'hasPayment' => Payment::forMerchant($merchant, $mode)->exists(),
        ]);
    }
}
