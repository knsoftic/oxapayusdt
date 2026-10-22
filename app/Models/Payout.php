<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payout extends Model
{
    public const STATUS_PENDING = 'pending';       // waiting for admin approval

    public const STATUS_PROCESSING = 'processing'; // sent to OxaPay, not confirmed yet

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';     // declined by admin, balance returned

    public const STATUS_FAILED = 'failed';         // OxaPay failed it, balance returned

    public const FINAL_STATUSES = [self::STATUS_COMPLETED, self::STATUS_REJECTED, self::STATUS_FAILED];

    protected $fillable = [
        'public_id',
        'merchant_id',
        'mode',
        'status',
        'amount',
        'fee',
        'net_amount',
        'currency',
        'network',
        'address',
        'memo',
        'track_id',
        'tx_hash',
        'note',
        'idempotency_key',
        'processed_by',
        'processed_at',
        'completed_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'payload' => 'array',
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public static function newPublicId(): string
    {
        return 'po_'.Str::lower(Str::ulid());
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function scopeForMerchant(Builder $query, Merchant $merchant, string $mode): void
    {
        $query->where('merchant_id', $merchant->id)->where('mode', $mode);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    public function networkLabel(): string
    {
        return config('platform.payout_networks')[$this->network] ?? $this->network;
    }
}
