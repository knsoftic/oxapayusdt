<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Services\Ledger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MerchantController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $merchants = Merchant::with('user')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('business_name', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($q) => $q->where('email', 'like', "%{$search}%"))))
            ->when($request->query('filter') === 'requests', fn ($q) => $q->where('live_enabled', false)->whereNotNull('live_requested_at'))
            ->withCount(['payments as paid_count' => fn ($q) => $q->where('mode', 'live')->whereIn('status', ['paid', 'manual_accept'])])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.merchants.index', compact('merchants', 'search'));
    }

    public function show(Merchant $merchant): View
    {
        return view('admin.merchants.show', [
            'merchant' => $merchant->load('user'),
            'payments' => $merchant->payments()->where('mode', 'live')->latest('id')->limit(10)->get(),
            'payouts' => $merchant->payouts()->latest('id')->limit(10)->get(),
            'entries' => $merchant->ledgerEntries()->latest('id')->limit(15)->get(),
        ]);
    }

    public function updateFees(Request $request, Merchant $merchant): RedirectResponse
    {
        $data = $request->validate([
            'fee_payin_percent' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'fee_payin_fixed' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'fee_payout_fixed' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $merchant->update($data);

        return back()->with('status', 'Fees saved. They apply to new payments and withdrawals.');
    }

    public function toggleLive(Merchant $merchant): RedirectResponse
    {
        $merchant->update(['live_enabled' => ! $merchant->live_enabled]);

        return back()->with('status', $merchant->live_enabled ? 'Live payments activated.' : 'Live payments turned off.');
    }

    public function toggleStatus(Merchant $merchant): RedirectResponse
    {
        $merchant->update([
            'status' => $merchant->isActive() ? Merchant::STATUS_SUSPENDED : Merchant::STATUS_ACTIVE,
        ]);

        return back()->with('status', $merchant->isActive() ? 'Merchant reactivated.' : 'Merchant suspended. Their API keys and logins stop working.');
    }

    /**
     * Manual balance correction, e.g. for an underpaid payment settled by hand.
     */
    public function adjust(Request $request, Merchant $merchant, Ledger $ledger): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['test', 'live'])],
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'note' => ['required', 'string', 'max:200'],
        ]);

        $cents = Money::cents($data['amount']);

        if ($data['direction'] === 'debit') {
            if ($cents > Money::cents($merchant->balance($data['mode']))) {
                return back()->withErrors(['amount' => 'A debit cannot be more than the current balance.']);
            }

            $cents = -$cents;
        }

        $ledger->adjust($merchant, $data['mode'], $cents, $data['note'], $request->user()->id);

        return back()->with('status', 'Balance adjusted by '.Money::usd($cents / 100).'.');
    }
}
