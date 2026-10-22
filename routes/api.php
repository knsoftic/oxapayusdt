<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Merchant API v1  (https://<host>/api/v1/...)
|--------------------------------------------------------------------------
|
| Authenticated with the merchant's secret key (sk_test_... / sk_live_...).
| Documented on the /docs page.
|
*/

Route::prefix('v1')->middleware(['api.key', 'throttle:merchant-api'])->group(function () {
    Route::post('/payments', [V1\PaymentController::class, 'store']);
    Route::get('/payments', [V1\PaymentController::class, 'index']);
    Route::get('/payments/{id}', [V1\PaymentController::class, 'show']);

    Route::get('/balance', V1\BalanceController::class);

    Route::post('/payouts', [V1\PayoutController::class, 'store']);
    Route::get('/payouts', [V1\PayoutController::class, 'index']);
    Route::get('/payouts/{id}', [V1\PayoutController::class, 'show']);
});
