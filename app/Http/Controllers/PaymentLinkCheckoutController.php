<?php

namespace App\Http\Controllers;

use App\Models\PaymentLink;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public page behind a merchant's payment link: creates a payment, then
 * hands over to the normal checkout.
 */
class PaymentLinkCheckoutController extends Controller
{
    public function show(PaymentLink $link): View
    {
        $this->ensureUsable($link);

        return view('checkout.link', ['link' => $link, 'merchant' => $link->merchant]);
    }

    public function store(Request $request, PaymentLink $link, PaymentService $payments): RedirectResponse
    {
        $this->ensureUsable($link);

        $data = $request->validate([
            'amount' => $link->amount ? ['prohibited'] : ['required', 'numeric', 'min:'.config('platform.min_payment'), 'max:1000000'],
            'customer_email' => ['nullable', 'email', 'max:255'],
        ]);

        $payment = $payments->create($link->merchant, $link->mode, [
            'amount' => $link->amount ?? $data['amount'],
            'description' => $link->title,
            'customer_email' => $data['customer_email'] ?? null,
            'payment_link_id' => $link->id,
        ]);

        return redirect()->route('checkout.show', $payment);
    }

    protected function ensureUsable(PaymentLink $link): void
    {
        $merchant = $link->merchant;

        abort_unless($link->active && $merchant->isActive() && ($link->mode === 'test' || $merchant->live_enabled), 404);
    }
}
