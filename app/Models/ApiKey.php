<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = ['merchant_id', 'mode', 'key_hash', 'hint', 'last_used_at', 'revoked_at'];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Revoke the merchant's current key for this mode and issue a new one.
     *
     * @return string The plain key. It is never stored and can't be shown again.
     */
    public static function roll(Merchant $merchant, string $mode): string
    {
        $plain = "sk_{$mode}_".Str::random(40);

        static::where('merchant_id', $merchant->id)
            ->where('mode', $mode)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        static::create([
            'merchant_id' => $merchant->id,
            'mode' => $mode,
            'key_hash' => hash('sha256', $plain),
            'hint' => substr($plain, -4),
        ]);

        return $plain;
    }

    public static function findActive(string $plain): ?self
    {
        return static::with('merchant')
            ->where('key_hash', hash('sha256', $plain))
            ->whereNull('revoked_at')
            ->first();
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function masked(): string
    {
        return "sk_{$this->mode}_••••••••••••".$this->hint;
    }
}
