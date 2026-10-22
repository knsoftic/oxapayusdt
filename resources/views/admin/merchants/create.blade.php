@extends('layouts.portal')

@section('title', 'Add merchant')
@section('subtitle', 'Open an account for a business; merchants cannot sign up themselves')

@section('content')
    <form method="POST" action="{{ route('admin.merchants.store') }}" class="stack" style="gap:20px">
        @csrf

        <div class="grid grid-2">
            <div class="card">
                <div class="card-head"><h2>Login</h2></div>
                <div class="card-body form">
                    <div class="field">
                        <label for="name">Contact name</label>
                        <input class="input" id="name" name="name" value="{{ old('name') }}" required maxlength="255" autofocus>
                    </div>
                    <div class="field">
                        <label for="email">Login email</label>
                        <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required>
                    </div>
                    <div class="field">
                        <label for="password">Password</label>
                        <div class="input-group">
                            <input class="input mono" id="password" name="password" type="text" value="{{ old('password') }}" required minlength="8" autocomplete="new-password" spellcheck="false">
                            <button type="button" class="btn btn-secondary" data-generate="password">Generate</button>
                        </div>
                        <div class="hint">Copy it and send it to the merchant privately. They can change it in Settings.</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>Business</h2></div>
                <div class="card-body form">
                    <div class="field">
                        <label for="business_name">Business name</label>
                        <input class="input" id="business_name" name="business_name" value="{{ old('business_name') }}" required maxlength="120">
                        <div class="hint">Shown to customers on the checkout page.</div>
                    </div>
                    <div class="field">
                        <label for="website">Website <span class="muted">(optional)</span></label>
                        <input class="input" id="website" name="website" type="url" value="{{ old('website') }}" placeholder="https://theirshop.com">
                    </div>
                    <label class="check">
                        <input type="checkbox" name="live_enabled" value="1" @checked(old('live_enabled'))>
                        <span>Activate live payments now <span class="muted small">(otherwise test mode only until you activate it)</span></span>
                    </label>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Fees for this merchant</h2></div>
            <div class="card-body form">
                @include('admin.merchants._fee_fields', ['merchant' => null])
            </div>
        </div>

        <div class="row">
            <button type="submit" class="btn"><x-icon name="plus" size="15" /> Create merchant</button>
            <a href="{{ route('admin.merchants.index') }}" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
@endsection
