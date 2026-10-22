<?php

namespace App\Http\Controllers\Merchant;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends MerchantController
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q'));

        $payments = Payment::forMerchant($this->merchant(), $this->mode())
            ->when(array_key_exists((string) $status, Payment::PUBLIC_STATUSES), fn ($q) => $q->withPublicStatus($status))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('public_id', $search)
                ->orWhere('reference', 'like', "%{$search}%")
                ->orWhere('customer_email', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('merchant.payments.index', compact('payments', 'status', 'search'));
    }

    public function show(Payment $payment, PaymentService $service): View
    {
        abort_unless($payment->merchant_id === $this->merchant()->id, 404);

        $service->sync($payment);

        return view('merchant.payments.show', [
            'payment' => $payment,
            'entries' => $payment->ledgerEntries()->orderBy('id')->get(),
        ]);
    }
}
