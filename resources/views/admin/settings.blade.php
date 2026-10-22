@extends('layouts.portal')

@section('title', 'Settings')
@section('subtitle', 'Your OxaPay account, fees and branding')

@section('content')
    @if (session('test_ok'))
        <div class="alert alert-ok"><x-icon name="check" /><p>{{ session('test_ok') }}</p></div>
    @endif
    @if (session('test_error'))
        <div class="alert alert-bad"><x-icon name="alert" /><p>{{ session('test_error') }}</p></div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}" class="stack" style="gap:20px">
        @csrf
        @method('PUT')

        <div class="grid grid-2">
            <div class="card">
                <div class="card-head"><h2>OxaPay account</h2><div class="right">@if ($merchantKey)<span class="badge ok">Connected</span>@else<span class="badge bad">Not connected</span>@endif</div></div>
                <div class="card-body form">
                    <p class="small muted mt-0">This is the main account: every merchant's payments land here, and withdrawals are sent from it.</p>
                    <div class="field">
                        <label for="merchant_api_key">Merchant API key</label>
                        <input class="input" id="merchant_api_key" name="merchant_api_key" autocomplete="off" spellcheck="false" placeholder="{{ $merchantKey ? 'Saved: '.$merchantKey.' (leave blank to keep)' : 'Paste your Merchant API Key' }}">
                        <div class="hint">OxaPay → <a href="https://app.oxapay.com/merchant-service" target="_blank" rel="noopener">Merchant Service</a> → Generate Merchant API Key</div>
                    </div>
                    <div class="field">
                        <label for="payout_api_key">Payout API key</label>
                        <input class="input" id="payout_api_key" name="payout_api_key" autocomplete="off" spellcheck="false" placeholder="{{ $payoutKey ? 'Saved: '.$payoutKey.' (leave blank to keep)' : 'Needed to send withdrawals automatically' }}">
                        <div class="hint">OxaPay → <a href="https://app.oxapay.com/payout-service" target="_blank" rel="noopener">Payout Service</a> → Generate Payout API Key</div>
                    </div>
                    <div class="field">
                        <label for="callback_url">Webhook URL override <span class="muted">(optional)</span></label>
                        <input class="input" id="callback_url" name="callback_url" type="url" value="{{ old('callback_url', config('oxapay.callback_url')) }}" placeholder="https://your-domain.com/oxapay/webhook">
                        <div class="hint">OxaPay sends updates to <code>{{ $webhookUrl }}</code>. It must be public https:// to work.</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>Fees</h2></div>
                <div class="card-body form">
                    <p class="small muted mt-0">Defaults for every merchant. You can override them per merchant. Keep them above OxaPay's own fee.</p>
                    <div class="form-row">
                        <div class="field">
                            <label for="payin_percent">Pay-in %</label>
                            <input class="input" id="payin_percent" name="payin_percent" type="number" step="0.01" min="0" max="50" value="{{ old('payin_percent', config('platform.fees.payin_percent')) }}" required>
                        </div>
                        <div class="field">
                            <label for="payin_fixed">Pay-in fixed $</label>
                            <input class="input" id="payin_fixed" name="payin_fixed" type="number" step="0.01" min="0" value="{{ old('payin_fixed', config('platform.fees.payin_fixed')) }}" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="payout_fixed">Payout fixed $</label>
                            <input class="input" id="payout_fixed" name="payout_fixed" type="number" step="0.01" min="0" value="{{ old('payout_fixed', config('platform.fees.payout_fixed')) }}" required>
                        </div>
                        <div class="field">
                            <label for="min_payment">Minimum payment $</label>
                            <input class="input" id="min_payment" name="min_payment" type="number" step="0.01" min="0.5" value="{{ old('min_payment', config('platform.min_payment')) }}" required>
                        </div>
                        <div class="field">
                            <label for="min_payout">Minimum withdrawal $</label>
                            <input class="input" id="min_payout" name="min_payout" type="number" step="0.01" min="1" value="{{ old('min_payout', config('platform.min_payout')) }}" required>
                        </div>
                    </div>
                    <input type="hidden" name="auto_convert_usdt" value="0">
                    <label class="check">
                        <input type="checkbox" name="auto_convert_usdt" value="1" @checked(old('auto_convert_usdt', config('platform.auto_convert_usdt')))>
                        <span>Convert received crypto to USDT automatically <span class="muted small">(recommended: protects merchant balances from price swings)</span></span>
                    </label>
                </div>
            </div>
        </div>

        <div class="grid grid-2">
            <div class="card">
                <div class="card-head"><h2>Checkout</h2></div>
                <div class="card-body form">
                    <div class="radio-cards">
                        <label class="radio-card">
                            <input type="radio" name="checkout" value="custom" @checked(old('checkout', config('oxapay.checkout')) !== 'hosted')>
                            <span><strong>Custom checkout</strong><span>Coin picker, address and QR code on your own branded page (OxaPay white-label).</span></span>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="checkout" value="hosted" @checked(old('checkout', config('oxapay.checkout')) === 'hosted')>
                            <span><strong>OxaPay hosted page</strong><span>Customers pay on pay.oxapay.com.</span></span>
                        </label>
                    </div>
                    <p class="small muted mt-0">This applies to live payments. Test-mode payments always use OxaPay's sandbox page, because white-label has no sandbox.</p>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>Branding</h2></div>
                <div class="card-body form">
                    <div class="form-row">
                        <div class="field">
                            <label for="platform_name">Platform name</label>
                            <input class="input" id="platform_name" name="platform_name" value="{{ old('platform_name', config('platform.name')) }}" required maxlength="60">
                        </div>
                        <div class="field">
                            <label for="brand_color">Brand colour</label>
                            <input class="input" id="brand_color" name="brand_color" type="color" value="{{ old('brand_color', config('platform.brand_color')) }}">
                        </div>
                    </div>
                    <p class="small muted mt-0">Used in the portal, emails and docs. Each merchant's checkout uses their own name and colour.</p>
                </div>
            </div>
        </div>

        <div class="row">
            <button type="submit" class="btn">Save settings</button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.settings.test') }}">
        @csrf
        <button type="submit" class="btn btn-secondary" @disabled(! $merchantKey)><x-icon name="bolt" size="15" /> Test OxaPay connection</button>
    </form>
@endsection
