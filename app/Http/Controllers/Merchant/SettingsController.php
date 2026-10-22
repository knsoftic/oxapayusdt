<?php

namespace App\Http\Controllers\Merchant;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends MerchantController
{
    public function edit(): View
    {
        return view('merchant.settings');
    }

    public function updateBusiness(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'brand_color.regex' => 'The brand colour must look like #4f46e5.',
        ]);

        $this->merchant()->update($data);

        return back()->with('status', 'Business details saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Password changed.');
    }

    public function requestLive(): RedirectResponse
    {
        $merchant = $this->merchant();

        if (! $merchant->live_enabled && ! $merchant->live_requested_at) {
            $merchant->update(['live_requested_at' => now()]);
        }

        return back()->with('status', 'Thanks! We will review your account for live payments.');
    }

    public function switchMode(Request $request): RedirectResponse
    {
        $mode = $request->validate(['mode' => ['required', Rule::in(['test', 'live'])]])['mode'];

        $request->session()->put('mode', $mode);

        return back();
    }
}
