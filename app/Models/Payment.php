<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Payment extends Model
{
    /**
     * OxaPay statuses that mean the money has been received.
     */
    public const PAID_STATUSES = ['paid', 'manual_accept'];

    /**
     * Statuses after which a payment no longer changes on its own.
     */
    public const FINAL_STATUSES = ['paid', 'manual_accept', 'expired', 'refunded', 'failed'];

    /**
     * Statuses shown to merchants (API, webhooks, portal) => raw statuses.
     */
    public const PUBLIC_STATUSES = [
        'pending' => ['pending', 'new', 'waiting'],
        'confirming' => ['paying'],
        'paid' => ['paid', 'manual_accept'],
        'underpaid' => ['underpaid'],
        'expired' => ['expired'],
        'refunded' => ['refunding', 'refunded'],
        'failed' => ['failed'],
    ];

    protected $fillable = [
        'public_id',
        'merchant_id',
        'payment_link_id',
        'mode',
        'status',
        'amount',
        'currency',
        'fee',
        'net_amount',
        'reference',
        'description',
        'customer_email',
        'callback_url',
        'return_url',
        'metadata',
        'idempotency_key',
        'type',
        'track_id',
        'payment_url',
        'pay_currency',
        'pay_amount',
        'network',
        'address',
        'memo',
        'qr_code',
        'expires_at',
        'paid_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'metadata' => 'array',
            'payload' => 'array',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public static function newPublicId(): string
    {
        return 'pay_'.Str::lower(Str::ulid());
    }

    /**
     * Use the public ID in URLs instead of the sequential primary key.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function paymentLink(): BelongsTo
    {
        return $this->belongsTo(PaymentLink::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function scopeForMerchant(Builder $query, Merchant $merchant, string $mode): void
    {
        $query->where('merchant_id', $merchant->id)->where('mode', $mode);
    }

    public function scopeWithPublicStatus(Builder $query, string $status): void
    {
        $query->whereIn('status', self::PUBLIC_STATUSES[$status] ?? [$status]);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, self::PAID_STATUSES, true);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    public function isTest(): bool
    {
        return $this->mode === 'test';
    }

    /**
     * Paid on our own checkout page rather than OxaPay's hosted page.
     */
    public function isWhiteLabel(): bool
    {
        return $this->type === 'white_label';
    }

    /**
     * Not yet sent to OxaPay: the customer hasn't started paying.
     */
    public function isAwaitingStart(): bool
    {
        return $this->status === 'pending' && ! $this->track_id;
    }

    public function publicStatus(): string
    {
        foreach (self::PUBLIC_STATUSES as $public => $raw) {
            if (in_array($this->status, $raw, true)) {
                return $public;
            }
        }

        return $this->status;
    }
}
