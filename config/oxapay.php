<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OxaPay API Keys
    |--------------------------------------------------------------------------
    |
    | This is the platform's own (master) OxaPay account: every merchant's
    | payments land here. The merchant key creates payments and verifies
    | payment webhooks; the payout key sends merchant withdrawals and verifies
    | payout webhooks. Both can also be saved from Admin > Settings.
    |
    */

    'merchant_api_key' => env('OXAPAY_MERCHANT_API_KEY'),

    'payout_api_key' => env('OXAPAY_PAYOUT_API_KEY'),

    'base_url' => env('OXAPAY_BASE_URL', 'https://api.oxapay.com/v1'),

    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    |
    | Live payments: "custom" shows the coin picker, address and QR code on
    | our own checkout (OxaPay white-label API); "hosted" sends the customer
    | to OxaPay's page. Test-mode payments always use OxaPay's hosted sandbox
    | page, because the white-label API has no sandbox.
    |
    */

    'checkout' => env('OXAPAY_CHECKOUT', 'custom'),

    /*
    |--------------------------------------------------------------------------
    | Webhook (Callback) URL
    |--------------------------------------------------------------------------
    |
    | OxaPay only delivers callbacks to public HTTPS URLs, so localhost will
    | not work. When empty, the URL of the "oxapay.webhook" route is used.
    |
    */

    'callback_url' => env('OXAPAY_CALLBACK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Payment Defaults
    |--------------------------------------------------------------------------
    |
    | lifetime: minutes a payment stays open (15 - 2880).
    | fee_paid_by_payer: 1 = payer pays OxaPay's fee, 0 = platform pays.
    | under_paid_coverage: accepted underpayment percentage (0 - 60).
    | Leave a value null to use the setting from your OxaPay dashboard.
    |
    */

    'lifetime' => (int) env('OXAPAY_LIFETIME', 60),

    'fee_paid_by_payer' => is_numeric($fee = env('OXAPAY_FEE_PAID_BY_PAYER')) ? (float) $fee : null,

    'under_paid_coverage' => is_numeric($coverage = env('OXAPAY_UNDER_PAID_COVERAGE')) ? (float) $coverage : null,

    'timeout' => (int) env('OXAPAY_TIMEOUT', 30),

];
