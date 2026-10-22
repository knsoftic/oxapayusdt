<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps admins and merchants in their own area, logs out suspended merchants,
 * and shares the merchant + current mode (test/live) with portal views.
 */
class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if ($user->role !== $role) {
            return redirect($user->homeRoute());
        }

        if ($user->isMerchant()) {
            // Always read the merchant fresh so a suspension takes effect on the very next request.
            $merchant = $user->merchant()->first();
            $user->setRelation('merchant', $merchant);

            if (! $merchant?->isActive()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['email' => 'Your account is suspended. Please contact support.']);
            }

            $request->attributes->set('merchant', $merchant);
            $request->attributes->set('mode', $request->session()->get('mode', 'test') === 'live' ? 'live' : 'test');

            View::share('merchant', $merchant);
            View::share('mode', $request->attributes->get('mode'));
        }

        return $next($request);
    }
}
