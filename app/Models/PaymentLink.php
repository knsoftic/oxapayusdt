<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PaymentLink extends Model
{
    protected $fillable = ['merchant_id', 'slug', 'mode', 'title', 'description', 'amount', 'active'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PaymentLink $link) {
            $link->slug ??= Str::lower(Str::random(12));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function url(): string
    {
        return route('links.show', $this);
    }
}
