<?php

namespace App\Http\Middleware;

use App\Http\ApiError;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates merchant API calls with "Authorization: Bearer sk_test_..." (or
 * an X-API-Key header). The key decides the merchant and test/live mode.
 */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?: $request->header('X-API-Key');

        if (blank($token)) {
            return ApiError::json(401, 'authentication_error', 'No API key provided. Send it as "Authorization: Bearer <your key>".');
        }

        $key = ApiKey::findActive($token);

        if (! $key) {
            return ApiError::json(401, 'authentication_error', 'Invalid or revoked API key.');
        }

        $merchant = $key->merchant;

        if (! $merchant->isActive()) {
            return ApiError::json(403, 'account_suspended', 'This merchant account is suspended.');
        }

        if ($key->mode === 'live' && ! $merchant->live_enabled) {
            return ApiError::json(403, 'live_mode_disabled', 'Live mode is not activated for this account yet. Use your test key, or request activation in the dashboard.');
        }

        if (! $key->last_used_at || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('merchant', $merchant);
        $request->attributes->set('mode', $key->mode);
        $request->attributes->set('api_key_id', $key->id);

        return $next($request);
    }
}
