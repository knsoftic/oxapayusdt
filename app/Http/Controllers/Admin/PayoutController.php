<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Services\OxaPay\OxaPayException;
use App\Services\PayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayoutController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', Payout::STATUS_PENDING);

        $payouts = Payout::with(['merchant', 'processedBy'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN mode = 'live' THEN 0 ELSE 1 END")
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.payouts', compact('payouts', 'status'));
    }

    public function approve(Request $request, Payout $payout, PayoutService $service): RedirectResponse
    {
        try {
            $service->approve($payout, $request->user());
        } catch (OxaPayException $e) {
            return back()->withErrors(['payout' => "OxaPay couldn't send {$payout->public_id}: {$e->getMessage()}"]);
        }

        return back()->with('status', "Payout {$payout->public_id} sent to OxaPay.");
    }

    public function markPaid(Request $request, Payout $payout, PayoutService $service): RedirectResponse
    {
        $data = $request->validate(['tx_hash' => ['nullable', 'string', 'max:120']]);

        $service->markPaid($payout, $request->user(), $data['tx_hash'] ?? null);

        return back()->with('status', "Payout {$payout->public_id} marked as paid.");
    }

    public function reject(Request $request, Payout $payout, PayoutService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);

        $service->reject($payout, $request->user(), $data['reason']);

        return back()->with('status', "Payout {$payout->public_id} rejected and the balance returned.");
    }
}
