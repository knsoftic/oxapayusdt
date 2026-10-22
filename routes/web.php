<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\Merchant;
use App\Http\Controllers\OxaPayWebhookController;
use App\Http\Controllers\PaymentLinkCheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect(auth()->user()->homeRoute()) : redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Customer-facing checkout
|--------------------------------------------------------------------------
*/

Route::get('/pay/{link}', [PaymentLinkCheckoutController::class, 'show'])->name('links.show');
Route::post('/pay/{link}', [PaymentLinkCheckoutController::class, 'store'])->middleware('throttle:20,1')->name('links.pay');

Route::get('/checkout/{payment}', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout/{payment}', [CheckoutController::class, 'start'])->middleware('throttle:20,1')->name('checkout.start');
Route::get('/checkout/{payment}/status', [CheckoutController::class, 'status'])->middleware('throttle:60,1')->name('checkout.status');

// Excluded from CSRF verification in bootstrap/app.php; secured by the HMAC signature instead.
Route::post('/oxapay/webhook', OxaPayWebhookController::class)->name('oxapay.webhook');

Route::get('/docs', DocsController::class)->name('docs');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/setup', [AuthController::class, 'showSetup'])->name('setup');
    Route::post('/setup', [AuthController::class, 'setup']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');
});

/*
|--------------------------------------------------------------------------
| Merchant portal
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:merchant'])->group(function () {
    Route::get('/dashboard', Merchant\DashboardController::class)->name('dashboard');
    Route::post('/mode', [Merchant\SettingsController::class, 'switchMode'])->name('mode.switch');

    Route::get('/payments', [Merchant\PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{payment}', [Merchant\PaymentController::class, 'show'])->name('payments.show');

    Route::get('/links', [Merchant\PaymentLinkController::class, 'index'])->name('links.index');
    Route::post('/links', [Merchant\PaymentLinkController::class, 'store'])->name('links.store');
    Route::patch('/links/{link}', [Merchant\PaymentLinkController::class, 'toggle'])->name('links.toggle');

    Route::get('/balance', [Merchant\BalanceController::class, 'index'])->name('balance.index');
    Route::get('/payouts', [Merchant\PayoutController::class, 'index'])->name('payouts.index');
    Route::post('/payouts', [Merchant\PayoutController::class, 'store'])->name('payouts.store');

    Route::get('/developers', [Merchant\DeveloperController::class, 'index'])->name('developers.index');
    Route::post('/developers/keys', [Merchant\DeveloperController::class, 'rollKey'])->name('developers.keys.roll');
    Route::put('/developers/webhook', [Merchant\DeveloperController::class, 'updateWebhook'])->name('developers.webhook');
    Route::post('/developers/webhook/secret', [Merchant\DeveloperController::class, 'rollSecret'])->name('developers.webhook.secret');
    Route::post('/developers/webhook/test', [Merchant\DeveloperController::class, 'testWebhook'])->name('developers.webhook.test');

    Route::get('/settings', [Merchant\SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings/business', [Merchant\SettingsController::class, 'updateBusiness'])->name('settings.business');
    Route::put('/settings/password', [Merchant\SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/settings/live', [Merchant\SettingsController::class, 'requestLive'])->name('settings.live');
});

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    // Merchants can't sign up themselves; the admin creates their accounts.
    Route::get('/merchants', [Admin\MerchantController::class, 'index'])->name('merchants.index');
    Route::get('/merchants/create', [Admin\MerchantController::class, 'create'])->name('merchants.create');
    Route::post('/merchants', [Admin\MerchantController::class, 'store'])->name('merchants.store');
    Route::get('/merchants/{merchant}', [Admin\MerchantController::class, 'show'])->name('merchants.show');
    Route::put('/merchants/{merchant}/account', [Admin\MerchantController::class, 'updateAccount'])->name('merchants.account');
    Route::put('/merchants/{merchant}/fees', [Admin\MerchantController::class, 'updateFees'])->name('merchants.fees');
    Route::post('/merchants/{merchant}/live', [Admin\MerchantController::class, 'toggleLive'])->name('merchants.live');
    Route::post('/merchants/{merchant}/status', [Admin\MerchantController::class, 'toggleStatus'])->name('merchants.status');
    Route::post('/merchants/{merchant}/adjust', [Admin\MerchantController::class, 'adjust'])->name('merchants.adjust');

    Route::get('/payments', [Admin\PaymentController::class, 'index'])->name('payments.index');

    Route::get('/payouts', [Admin\PayoutController::class, 'index'])->name('payouts.index');
    Route::post('/payouts/{payout}/approve', [Admin\PayoutController::class, 'approve'])->name('payouts.approve');
    Route::post('/payouts/{payout}/mark-paid', [Admin\PayoutController::class, 'markPaid'])->name('payouts.paid');
    Route::post('/payouts/{payout}/reject', [Admin\PayoutController::class, 'reject'])->name('payouts.reject');

    Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/test', [Admin\SettingsController::class, 'test'])->name('settings.test');
});
