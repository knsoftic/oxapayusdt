@extends('layouts.portal')

@section('title', 'Settings')
@section('subtitle', 'Business details, live activation and your password')

@section('content')
    <div class="grid grid-2">
        <div class="card">
            <div class="card-head"><h2>Business</h2></div>
            <div class="card-body">
                <form method="POST" action="{{ route('settings.business') }}" class="form">
                    @csrf
                    @method('PUT')
                    <div class="field">
                        <label for="business_name">Business name</label>
                        <input class="input" id="business_name" name="business_name" value="{{ old('business_name', $merchant->business_name) }}" required maxlength="120">
                        <div class="hint">Shown to your customers on the checkout page.</div>
                    </div>
                    <div class="field">
                        <label for="website">Website <span class="muted">(optional)</span></label>
                        <input class="input" id="website" name="website" type="url" value="{{ old('website', $merchant->website) }}" placeholder="https://yourshop.com">
                    </div>
                    <div class="field">
                        <label for="brand_color">Checkout colour</label>
                        <input class="input" id="brand_color" name="brand_color" type="color" value="{{ old('brand_color', $merchant->brand_color ?: config('platform.brand_color')) }}">
                        <div class="hint">Buttons and highlights on your checkout use this colour.</div>
                    </div>
                    <div><button type="submit" class="btn">Save</button></div>
                </form>
            </div>
        </div>

        <div class="stack">
            <div class="card" id="live">
                <div class="card-head">
                    <h2>Live payments</h2>
                    <div class="right">
                        @if ($merchant->live_enabled)
                            <span class="badge ok">Active</span>
                        @elseif ($merchant->live_requested_at)
                            <span class="badge warn">Under review</span>
                        @else
                            <span class="badge">Not requested</span>
                        @endif
                    </div>
                </div>
                <div class="card-body stack">
                    @if ($merchant->live_enabled)
                        <p class="mt-0">Your account accepts real payments. Switch to <strong>Live</strong> in the sidebar and create a live API key in <a href="{{ route('developers.index') }}">Developers</a>.</p>
                    @elseif ($merchant->live_requested_at)
                        <p class="mt-0">You asked for live access on {{ $merchant->live_requested_at->format('d M Y') }}. We'll enable it after review. Meanwhile, keep testing in test mode.</p>
                    @else
                        <p class="mt-0">When your integration works in test mode, ask us to turn on real payments.</p>
                        <form method="POST" action="{{ route('settings.live') }}" class="inline-form">
                            @csrf
                            <button type="submit" class="btn">Request live access</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>Your fees</h2></div>
                <div class="card-body">
                    <dl class="dl">
                        <dt>Per payment</dt><dd>{{ $merchant->payinFeeLabel() }}</dd>
                        <dt>Per withdrawal</dt><dd>{{ $merchant->payoutFeeLabel() }}</dd>
                        <dt>Example</dt><dd>A {{ usd(10) }} payment adds {{ usd(10 - (float) $merchant->payinFeeFor(10)) }} to your balance</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card-head"><h2>Password</h2></div>
            <div class="card-body">
                <form method="POST" action="{{ route('settings.password') }}" class="form">
                    @csrf
                    @method('PUT')
                    <div class="field">
                        <label for="current_password">Current password</label>
                        <input class="input" id="current_password" name="current_password" type="password" required autocomplete="current-password">
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="password">New password</label>
                            <input class="input" id="password" name="password" type="password" required autocomplete="new-password">
                        </div>
                        <div class="field">
                            <label for="password_confirmation">Confirm</label>
                            <input class="input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                        </div>
                    </div>
                    <div><button type="submit" class="btn btn-secondary">Change password</button></div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Account</h2></div>
            <div class="card-body">
                <dl class="dl">
                    <dt>Name</dt><dd>{{ auth()->user()->name }}</dd>
                    <dt>Email</dt><dd>{{ auth()->user()->email }} @if (auth()->user()->hasVerifiedEmail())<span class="badge ok">Verified</span>@else<span class="badge warn">Not verified</span>@endif</dd>
                    <dt>Member since</dt><dd>{{ $merchant->created_at->format('d M Y') }}</dd>
                </dl>
            </div>
        </div>
    </div>
@endsection
