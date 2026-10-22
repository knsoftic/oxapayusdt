<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'object' => 'payment',
            'mode' => $this->mode,
            'status' => $this->publicStatus(),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'fee' => $this->fee,
            'net_amount' => $this->net_amount,
            'reference' => $this->reference,
            'description' => $this->description,
            'customer_email' => $this->customer_email,
            'checkout_url' => route('checkout.show', $this->resource),
            'pay_currency' => $this->pay_currency,
            'pay_amount' => $this->pay_amount,
            'network' => $this->network,
            'metadata' => $this->metadata ?? (object) [],
            'expires_at' => $this->expires_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
