<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $payments)
    {
        //
    }

    /**
     * POST /api/v1/payments
     */
    public function store(Request $request): JsonResponse
    {
        $merchant = $request->attributes->get('merchant');
        $mode = $request->attributes->get('mode');

        $data = $request->validate([
            // The merchant's own minimum and maximum are checked when the payment is created.
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'currency' => ['sometimes', 'string', Rule::in(['USD', 'usd'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'callback_url' => ['nullable', 'url:http,https', 'max:500'],
            'return_url' => ['nullable', 'url:http,https', 'max:500'],
            'metadata' => ['nullable', 'array', 'max:20'],
            'metadata.*' => ['nullable', 'string', 'max:500'],
        ], [
            'currency.in' => 'Only USD amounts are supported.',
        ]);

        $idempotencyKey = $this->idempotencyKey($request);

        if ($idempotencyKey && $existing = $this->findByIdempotencyKey($merchant->id, $mode, $idempotencyKey)) {
            return PaymentResource::make($existing)->response()->setStatusCode(200);
        }

        try {
            $payment = $this->payments->create($merchant, $mode, $data + ['idempotency_key' => $idempotencyKey]);
        } catch (UniqueConstraintViolationException) {
            // The same Idempotency-Key raced in twice; return the one that won.
            return PaymentResource::make($this->findByIdempotencyKey($merchant->id, $mode, $idempotencyKey))->response()->setStatusCode(200);
        }

        return PaymentResource::make($payment)->response()->setStatusCode(201);
    }

    /**
     * GET /api/v1/payments
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Payment::PUBLIC_STATUSES))],
            'reference' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $payments = Payment::forMerchant($request->attributes->get('merchant'), $request->attributes->get('mode'))
            ->when($request->query('status'), fn ($q, $status) => $q->withPublicStatus($status))
            ->when($request->query('reference'), fn ($q, $reference) => $q->where('reference', $reference))
            ->latest('id')
            ->paginate($request->integer('limit', 20))
            ->withQueryString();

        return PaymentResource::collection($payments);
    }

    /**
     * GET /api/v1/payments/{id}
     */
    public function show(Request $request, string $id): PaymentResource
    {
        $payment = Payment::forMerchant($request->attributes->get('merchant'), $request->attributes->get('mode'))
            ->where('public_id', $id)
            ->firstOrFail();

        $this->payments->sync($payment);

        return PaymentResource::make($payment);
    }

    protected function idempotencyKey(Request $request): ?string
    {
        $key = $request->header('Idempotency-Key');
        abort_if($key !== null && strlen($key) > 100, 422, 'Idempotency-Key must be at most 100 characters.');

        return filled($key) ? $key : null;
    }

    protected function findByIdempotencyKey(int $merchantId, string $mode, string $key): ?Payment
    {
        return Payment::where('merchant_id', $merchantId)->where('mode', $mode)->where('idempotency_key', $key)->first();
    }
}
