<?php

namespace App\Models;

use App\Support\Money;
use Database\Factories\MerchantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Merchant extends Model
{
    /** @use HasFactory<MerchantFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'user_id',
        'business_name',
        'website',
        'brand_color',
        'status',
        'live_enabled',
        'live_requested_at',
        'fee_payin_percent',
        'fee_payin_fixed',
        'fee_payout_percent',
        'fee_payout_fixed',
        'webhook_url',
        'webhook_secret',
    ];

    protected $hidden = ['webhook_secret'];

    protected function casts(): array
    {
        return [
            'live_enabled' => 'boolean',
            'live_requested_at' => 'datetime',
            'fee_payin_percent' => 'decimal:2',
            'fee_payin_fixed' => 'decimal:2',
            'fee_payout_percent' => 'decimal:2',
            'fee_payout_fixed' => 'decimal:2',
            'live_balance' => 'decimal:2',
            'test_balance' => 'decimal:2',
            'webhook_secret' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Merchant $merchant) {
            $merchant->webhook_secret ??= self::newWebhookSecret();
        });
    }

    public static function newWebhookSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function paymentLinks(): HasMany
    {
        return $this->hasMany(PaymentLink::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function balance(string $mode): string
    {
        return $mode === 'live' ? $this->live_balance : $this->test_balance;
    }

    public function payinFeePercent(): float
    {
        return (float) ($this->fee_payin_percent ?? config('platform.fees.payin_percent'));
    }

    public function payinFeeFixed(): float
    {
        return (float) ($this->fee_payin_fixed ?? config('platform.fees.payin_fixed'));
    }

    public function payoutFeePercent(): float
    {
        return (float) ($this->fee_payout_percent ?? config('platform.fees.payout_percent'));
    }

    public function payoutFeeFixed(): float
    {
        return (float) ($this->fee_payout_fixed ?? config('platform.fees.payout_fixed'));
    }

    /**
     * Pay-in fee for an amount, never more than the amount itself.
     */
    public function payinFeeFor(int|float|string $amount): string
    {
        return self::feeFor($amount, $this->payinFeePercent(), $this->payinFeeFixed());
    }

    /**
     * Payout fee for a withdrawal amount, never more than the amount itself.
     */
    public function payoutFeeFor(int|float|string $amount): string
    {
        return self::feeFor($amount, $this->payoutFeePercent(), $this->payoutFeeFixed());
    }

    /**
     * Human-readable fee summary such as "1% + $0.50".
     */
    public function payinFeeLabel(): string
    {
        return self::feeLabel($this->payinFeePercent(), $this->payinFeeFixed());
    }

    public function payoutFeeLabel(): string
    {
        return self::feeLabel($this->payoutFeePercent(), $this->payoutFeeFixed());
    }

    public static function feeFor(int|float|string $amount, float $percent, float $fixed): string
    {
        $cents = Money::cents($amount);
        $fee = (int) round($cents * $percent / 100) + Money::cents($fixed);

        return Money::format(min($fee, $cents));
    }

    public static function feeLabel(float $percent, float $fixed): string
    {
        $parts = [];

        if ($percent > 0) {
            $parts[] = rtrim(rtrim(number_format($percent, 2), '0'), '.').'%';
        }

        if ($fixed > 0 || ! $parts) {
            $parts[] = Money::usd($fixed);
        }

        return implode(' + ', $parts);
    }
}
