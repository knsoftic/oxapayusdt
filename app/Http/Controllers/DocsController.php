<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Public developer documentation for the merchant API.
 */
class DocsController extends Controller
{
    public function __invoke(): View
    {
        return view('docs.index', [
            'baseUrl' => url('/api/v1'),
            'statuses' => [
                'pending' => 'Created; the customer has not paid yet.',
                'confirming' => 'Payment detected on the blockchain, waiting for confirmations.',
                'paid' => 'Fully paid. The net amount is now in your balance.',
                'underpaid' => 'The customer sent less than required. Contact support to settle it.',
                'expired' => 'Not paid in time.',
                'refunded' => 'The payment was refunded.',
                'failed' => 'The payment could not be created.',
            ],
        ]);
    }
}
