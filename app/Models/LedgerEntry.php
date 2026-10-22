<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    public const LABELS = [
        'payment' => 'Payment received',
        'payment_fee' => 'Pay-in fee',
        'payout' => 'Withdrawal',
        'payout_fee' => 'Payout fee',
        'payout_reversal' => 'Withdrawal returned',
        'payout_fee_reversal' => 'Payout fee returned',
        'adjustment' => 'Adjustment',
    ];

    protected $fillable = [
        'merchant_id',
        'mode',
        'type',
        'amount',
        'balance_after',
        'description',
        'payment_id',
        'payout_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }
}
