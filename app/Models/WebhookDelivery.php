<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'merchant_id',
        'event_id',
        'event',
        'mode',
        'url',
        'payload',
        'status',
        'attempts',
        'response_code',
        'response_body',
        'payment_id',
        'payout_id',
        'last_attempt_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'last_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
