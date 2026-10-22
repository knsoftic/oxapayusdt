<?php

namespace App\Http\Resources;

use App\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payout
 */
class PayoutResource extends JsonResource
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
            'object' => 'payout',
            'mode' => $this->mode,
            'status' => $this->status,
            'amount' => $this->amount,
            'fee' => $this->fee,
            'net_amount' => $this->net_amount,
            'currency' => $this->currency,
            'network' => $this->network,
            'address' => $this->address,
            'memo' => $this->memo,
            'tx_hash' => $this->tx_hash,
            'failure_reason' => in_array($this->status, [Payout::STATUS_REJECTED, Payout::STATUS_FAILED], true) ? $this->note : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
