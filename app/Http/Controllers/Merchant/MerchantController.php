<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Merchant;

/**
 * Base for merchant portal controllers. The "role:merchant" middleware puts
 * the signed-in merchant and the current mode (test/live) on the request.
 */
abstract class MerchantController extends Controller
{
    protected function merchant(): Merchant
    {
        return request()->attributes->get('merchant');
    }

    protected function mode(): string
    {
        return request()->attributes->get('mode');
    }
}
