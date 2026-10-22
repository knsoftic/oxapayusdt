<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;

/**
 * Settings saved from the admin settings page.
 *
 * Keys are config paths (e.g. "oxapay.merchant_api_key"), and saved values
 * override the matching .env-based config when the app boots.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        // Values include API keys, so they are encrypted at rest with APP_KEY.
        return ['value' => 'encrypted:json'];
    }

    /**
     * Save a setting. Null or an empty string removes it, so .env applies again.
     */
    public static function put(string $key, mixed $value): void
    {
        if ($value === null || $value === '') {
            static::where('key', $key)->delete();

            return;
        }

        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function applyToConfig(): void
    {
        foreach (static::all() as $setting) {
            try {
                config([$setting->key => $setting->value]);
            } catch (DecryptException) {
                // Saved under a different APP_KEY; skip it and fall back to .env.
            }
        }
    }
}
