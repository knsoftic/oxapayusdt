<?php

namespace App\Http\Controllers\Merchant;

use App\Models\Payout;
use App\Rules\CryptoAddress;
use App\Services\PayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PayoutController extends MerchantController
{
    public function index(): View
    {
        $merchant = $this->merchant();

        return view('merchant.payouts', [
            'balance' => $merchant->balance($this->mode()),
            'networks' => config('platform.payout_networks'),
            'payouts' => Payout::forMerchant($merchant, $this->mode())->latest('id')->paginate(15),
        ]);
    }

    public function store(Request $request, PayoutService $payouts): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'network' => ['required', Rule::in(array_keys(config('platform.payout_networks')))],
            'address' => ['required', 'string', 'max:120', new CryptoAddress($request->input('network'))],
            'memo' => ['nullable', 'string', 'max:100'],
        ]);

        $payout = $payouts->request($this->merchant(), $this->mode(), $data);

        return redirect()->route('payouts.index')->with('status', $payout->status === Payout::STATUS_COMPLETED
            ? 'Test withdrawal completed (simulated, nothing was sent).'
            : 'Withdrawal requested. It will be sent after review.');
    }
}
