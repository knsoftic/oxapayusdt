<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\OxaPay\OxaPayClient;
use App\Services\OxaPay\OxaPayException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Platform settings: the master OxaPay account, fees and branding.
 */
class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', [
            'merchantKey' => $this->mask(config('oxapay.merchant_api_key')),
            'payoutKey' => $this->mask(config('oxapay.payout_api_key')),
            'webhookUrl' => config('oxapay.callback_url') ?: route('oxapay.webhook'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'merchant_api_key' => ['nullable', 'string', 'max:255'],
            'payout_api_key' => ['nullable', 'string', 'max:255'],
            'callback_url' => ['nullable', 'url:https', 'max:255'],
            'checkout' => ['required', Rule::in(['custom', 'hosted'])],
            'platform_name' => ['required', 'string', 'max:60'],
            'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'payin_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'payin_fixed' => ['required', 'numeric', 'min:0', 'max:1000'],
            'payout_fixed' => ['required', 'numeric', 'min:0', 'max:1000'],
            'min_payment' => ['required', 'numeric', 'min:0.5', 'max:100000'],
            'min_payout' => ['required', 'numeric', 'min:1', 'max:100000'],
            'auto_convert_usdt' => ['boolean'],
        ], [
            'callback_url.url' => 'The webhook URL must be a public https:// address.',
            'brand_color.regex' => 'The brand colour must look like #4f46e5.',
        ]);

        // A blank key field keeps the saved key, so saved keys never have to be sent back to the browser.
        foreach (['merchant_api_key', 'payout_api_key'] as $field) {
            if (filled($data[$field] ?? null)) {
                Setting::put("oxapay.{$field}", trim($data[$field]));
            }
        }

        Setting::put('oxapay.callback_url', $data['callback_url'] ?? null);
        Setting::put('oxapay.checkout', $data['checkout']);
        Setting::put('platform.name', trim($data['platform_name']));
        Setting::put('platform.brand_color', strtolower($data['brand_color']));
        Setting::put('platform.fees.payin_percent', (float) $data['payin_percent']);
        Setting::put('platform.fees.payin_fixed', (float) $data['payin_fixed']);
        Setting::put('platform.fees.payout_fixed', (float) $data['payout_fixed']);
        Setting::put('platform.min_payment', (float) $data['min_payment']);
        Setting::put('platform.min_payout', (float) $data['min_payout']);
        Setting::put('platform.auto_convert_usdt', $request->boolean('auto_convert_usdt'));

        return redirect()->route('admin.settings.edit')->with('status', 'Settings saved.');
    }

    /**
     * Check both OxaPay keys without moving any money.
     */
    public function test(OxaPayClient $oxapay): RedirectResponse
    {
        try {
            $currencies = $oxapay->acceptedCurrencies();
        } catch (OxaPayException $e) {
            return redirect()->route('admin.settings.edit')->with('test_error', 'Merchant key: '.$e->getMessage());
        }

        $result = 'Merchant key works. Accepted currencies: '.(implode(', ', $currencies) ?: 'none enabled yet').'.';

        if ($oxapay->hasPayoutKey()) {
            try {
                $oxapay->payoutHistory(['size' => 1]);
                $result .= ' Payout key works.';
            } catch (OxaPayException $e) {
                return redirect()->route('admin.settings.edit')->with('test_error', $result.' Payout key: '.$e->getMessage());
            }
        } else {
            $result .= ' No payout key yet, so live withdrawals have to be paid by hand.';
        }

        return redirect()->route('admin.settings.edit')->with('test_ok', $result);
    }

    /**
     * Show only the last 4 characters of a saved key.
     */
    protected function mask(?string $key): ?string
    {
        if (blank($key)) {
            return null;
        }

        return str_repeat('•', 8).(strlen($key) > 12 ? substr($key, -4) : '');
    }
}
