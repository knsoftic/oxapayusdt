<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\OxaPay\OxaPayClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OxaPayClient::class, fn () => new OxaPayClient(
            merchantApiKey: config('oxapay.merchant_api_key'),
            payoutApiKey: config('oxapay.payout_api_key'),
            baseUrl: config('oxapay.base_url'),
            timeout: config('oxapay.timeout'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // OxaPay keys, fees and other options saved from Admin > Settings take priority over .env.
        try {
            Setting::applyToConfig();
        } catch (QueryException) {
            // The settings table doesn't exist yet (before the first migration).
        }

        // API objects are returned as-is: {"id": "pay_...", ...}, not {"data": {...}}.
        JsonResource::withoutWrapping();

        RateLimiter::for('merchant-api', function (Request $request) {
            return Limit::perMinute(120)->by('api-key:'.$request->attributes->get('api_key_id', $request->ip()));
        });
    }
}
