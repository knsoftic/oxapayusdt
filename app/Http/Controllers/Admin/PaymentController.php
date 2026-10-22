<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $mode = $request->query('mode') === 'test' ? 'test' : 'live';
        $status = $request->query('status');
        $search = trim((string) $request->query('q'));

        $payments = Payment::with('merchant')
            ->where('mode', $mode)
            ->when(array_key_exists((string) $status, Payment::PUBLIC_STATUSES), fn ($q) => $q->withPublicStatus($status))
            ->when($request->integer('merchant'), fn ($q, $id) => $q->where('merchant_id', $id))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('public_id', $search)
                ->orWhere('track_id', $search)
                ->orWhere('reference', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.payments', compact('payments', 'mode', 'status', 'search'));
    }
}
