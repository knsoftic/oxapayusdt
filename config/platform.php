<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform
    |--------------------------------------------------------------------------
    |
    | Name and colour of the gateway shown in the merchant portal, the admin
    | panel and the developer docs. Can be changed from Admin > Settings.
    |
    */

    'name' => env('PLATFORM_NAME', 'PayGate'),

    'brand_color' => env('PLATFORM_BRAND_COLOR', '#4f46e5'),

    /*
    |--------------------------------------------------------------------------
    | Fees (USD)
    |--------------------------------------------------------------------------
    |
    | Default fees for every merchant; each merchant can get its own override.
    | Pay-in fee = amount x payin_percent / 100 + payin_fixed, taken from each
    | paid payment. Payout fee = amount x payout_percent / 100 + payout_fixed,
    | taken from each withdrawal.
    |
    */

    'fees' => [
        'payin_percent' => (float) env('PLATFORM_PAYIN_FEE_PERCENT', 0),
        'payin_fixed' => (float) env('PLATFORM_PAYIN_FEE_FIXED', 0.50),
        'payout_percent' => (float) env('PLATFORM_PAYOUT_FEE_PERCENT', 0),
        'payout_fixed' => (float) env('PLATFORM_PAYOUT_FEE_FIXED', 1.00),
    ],

    'min_payment' => (float) env('PLATFORM_MIN_PAYMENT', 1.00),

    'min_payout' => (float) env('PLATFORM_MIN_PAYOUT', 5.00),

    /*
    |--------------------------------------------------------------------------
    | Settlement
    |--------------------------------------------------------------------------
    |
    | Balances are kept in USD. With auto_convert_usdt on, OxaPay converts
    | every crypto it receives to USDT, so price swings can't shrink what the
    | platform owes its merchants. Payouts are sent in USDT.
    |
    */

    'auto_convert_usdt' => (bool) env('PLATFORM_AUTO_CONVERT_USDT', true),

    'payout_currency' => 'USDT',

    // OxaPay network name => label shown to merchants.
    'payout_networks' => [
        'Tron' => 'Tron (TRC20)',
        'BSC' => 'BNB Smart Chain (BEP20)',
        'Ethereum' => 'Ethereum (ERC20)',
        'Polygon' => 'Polygon',
        'Solana' => 'Solana',
        'The Open Network' => 'TON',
    ],

    /*
    |--------------------------------------------------------------------------
    | Merchant Webhooks
    |--------------------------------------------------------------------------
    */

    'webhooks' => [
        'timeout' => 10,
        // Seconds to wait before each retry after a failed delivery.
        'backoff' => [60, 300, 1800, 3600, 10800],
    ],

];
