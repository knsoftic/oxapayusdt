@extends('layouts.auth')

@section('title', 'Create account')
@section('box_class', 'wide')
@section('heading', 'Start accepting crypto')
@section('lead', 'Create a merchant account. You can test right away; live payments open after a quick review.')

@section('content')
    <form method="POST" action="{{ route('register') }}" class="form">
        @csrf

        <div class="form-row">
            <div class="field">
                <label for="name">Your name</label>
                <input class="input" id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
            </div>
            <div class="field">
                <label for="business_name">Business name</label>
                <input class="input" id="business_name" type="text" name="business_name" value="{{ old('business_name') }}" required maxlength="120">
            </div>
        </div>

        <div class="field">
            <label for="email">Work email</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
        </div>

        <div class="form-row">
            <div class="field">
                <label for="password">Password</label>
                <input class="input" id="password" type="password" name="password" required autocomplete="new-password">
                <div class="hint">At least 8 characters.</div>
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input class="input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
        </div>

        <label class="check">
            <input type="checkbox" name="terms" value="1" @checked(old('terms')) required>
            <span>I agree to the terms of service and will only accept payments for legal goods and services.</span>
        </label>

        <button type="submit" class="btn btn-block">Create account</button>
    </form>
@endsection

@section('footer')
    Already have an account? <a href="{{ route('login') }}">Log in</a>
@endsection
