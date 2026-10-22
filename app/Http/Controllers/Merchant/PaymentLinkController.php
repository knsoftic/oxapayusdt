<?php

namespace App\Http\Controllers\Merchant;

use App\Models\PaymentLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shareable links that open a checkout, for merchants who don't use the API.
 */
class PaymentLinkController extends MerchantController
{
    public function index(): View
    {
        $links = PaymentLink::where('merchant_id', $this->merchant()->id)
            ->where('mode', $this->mode())
            ->withCount(['payments as paid_count' => fn ($q) => $q->whereIn('status', ['paid', 'manual_accept'])])
            ->latest('id')
            ->get();

        return view('merchant.links.index', compact('links'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:'.config('platform.min_payment'), 'max:1000000'],
        ]);

        PaymentLink::create($data + [
            'merchant_id' => $this->merchant()->id,
            'mode' => $this->mode(),
        ]);

        return redirect()->route('links.index')->with('status', 'Payment link created.');
    }

    public function toggle(PaymentLink $link): RedirectResponse
    {
        abort_unless($link->merchant_id === $this->merchant()->id, 404);

        $link->update(['active' => ! $link->active]);

        return back()->with('status', $link->active ? 'Link turned on.' : 'Link turned off.');
    }
}
