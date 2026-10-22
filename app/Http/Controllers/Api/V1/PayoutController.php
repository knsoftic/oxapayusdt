<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayoutResource;
use App\Models\Payout;
use App\Rules\CryptoAddress;
use App\Services\PayoutService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PayoutController extends Controller
{
    /**
     * POST /api/v1/payouts
     */
    public function store(Request $request, PayoutService $payouts): JsonResponse
    {
        $merchant = $request->attributes->get('merchant');
        $mode = $request->attributes->get('mode');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'network' => ['required', Rule::in(array_keys(config('platform.payout_networks')))],
            'address' => ['required', 'string', 'max:120', new CryptoAddress($request->input('network'))],
            'memo' => ['nullable', 'string', 'max:100'],
        ]);

        $key = $request->header('Idempotency-Key');
        abort_if($key !== null && strlen($key) > 100, 422, 'Idempotency-Key must be at most 100 characters.');
        $key = filled($key) ? $key : null;

        if ($key && $existing = $this->findByIdempotencyKey($merchant->id, $mode, $key)) {
            return PayoutResource::make($existing)->response()->setStatusCode(200);
        }

        try {
            $payout = $payouts->request($merchant, $mode, $data + ['idempotency_key' => $key]);
        } catch (UniqueConstraintViolationException) {
            return PayoutResource::make($this->findByIdempotencyKey($merchant->id, $mode, $key))->response()->setStatusCode(200);
        }

        return PayoutResource::make($payout)->response()->setStatusCode(201);
    }

    /**
     * GET /api/v1/payouts
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $payouts = Payout::forMerchant($request->attributes->get('merchant'), $request->attributes->get('mode'))
            ->latest('id')
            ->paginate($request->integer('limit', 20))
            ->withQueryString();

        return PayoutResource::collection($payouts);
    }

    /**
     * GET /api/v1/payouts/{id}
     */
    public function show(Request $request, string $id): PayoutResource
    {
        return PayoutResource::make(
            Payout::forMerchant($request->attributes->get('merchant'), $request->attributes->get('mode'))
                ->where('public_id', $id)
                ->firstOrFail()
        );
    }

    protected function findByIdempotencyKey(int $merchantId, string $mode, string $key): ?Payout
    {
        return Payout::where('merchant_id', $merchantId)->where('mode', $mode)->where('idempotency_key', $key)->first();
    }
}
