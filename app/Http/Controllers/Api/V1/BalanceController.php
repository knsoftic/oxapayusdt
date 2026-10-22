<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BalanceController extends Controller
{
    /**
     * GET /api/v1/balance
     */
    public function __invoke(Request $request): JsonResponse
    {
        $merchant = $request->attributes->get('merchant');
        $mode = $request->attributes->get('mode');

        $pending = Payout::forMerchant($merchant, $mode)
            ->whereIn('status', [Payout::STATUS_PENDING, Payout::STATUS_PROCESSING])
            ->sum('amount');

        return response()->json([
            'object' => 'balance',
            'mode' => $mode,
            'currency' => 'USD',
            'available' => $merchant->balance($mode),
            'pending_payouts' => Money::format(Money::cents($pending)),
            'fees' => [
                'payin_percent' => number_format($merchant->payinFeePercent(), 2, '.', ''),
                'payin_fixed' => number_format($merchant->payinFeeFixed(), 2, '.', ''),
                'payout_percent' => number_format($merchant->payoutFeePercent(), 2, '.', ''),
                'payout_fixed' => number_format($merchant->payoutFeeFixed(), 2, '.', ''),
            ],
        ]);
    }
}
