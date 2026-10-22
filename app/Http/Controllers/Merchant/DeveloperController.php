<?php

namespace App\Http\Controllers\Merchant;

use App\Models\ApiKey;
use App\Models\Merchant;
use App\Models\WebhookDelivery;
use App\Services\WebhookDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * API keys, webhook endpoint and recent webhook deliveries.
 */
class DeveloperController extends MerchantController
{
    public function index(): View
    {
        $merchant = $this->merchant();

        $keys = ApiKey::where('merchant_id', $merchant->id)
            ->whereNull('revoked_at')
            ->get()
            ->keyBy('mode');

        return view('merchant.developers', [
            'keys' => $keys,
            'deliveries' => WebhookDelivery::where('merchant_id', $merchant->id)
                ->where('mode', $this->mode())
                ->latest('id')
                ->limit(15)
                ->get(),
        ]);
    }

    public function rollKey(Request $request): RedirectResponse
    {
        $mode = $request->validate(['mode' => ['required', Rule::in(['test', 'live'])]])['mode'];
        $merchant = $this->merchant();

        if ($mode === 'live' && ! $merchant->live_enabled) {
            return back()->withErrors(['key' => 'Live keys are available once your account is activated for live payments.']);
        }

        $plain = ApiKey::roll($merchant, $mode);

        // Shown once on the next page; only a hash is stored.
        return back()->with('new_key', ['mode' => $mode, 'key' => $plain]);
    }

    public function updateWebhook(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'webhook_url' => ['nullable', 'url:http,https', 'max:255'],
        ]);

        $this->merchant()->update($data);

        return back()->with('status', 'Webhook URL saved.');
    }

    public function rollSecret(): RedirectResponse
    {
        $this->merchant()->update(['webhook_secret' => Merchant::newWebhookSecret()]);

        return back()->with('status', 'New signing secret created. Update it on your server.');
    }

    public function testWebhook(WebhookDispatcher $webhooks): RedirectResponse
    {
        $merchant = $this->merchant();

        if (blank($merchant->webhook_url)) {
            return back()->withErrors(['webhook_url' => 'Save a webhook URL first.']);
        }

        $webhooks->testEvent($merchant, $this->mode());

        return back()->with('status', 'Test event sent. Its result appears below in a few seconds.');
    }
}
