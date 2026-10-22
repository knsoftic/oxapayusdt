@extends('layouts.auth')

@section('title', 'Platform setup')
@section('heading', 'Create the admin account')
@section('lead', 'First-time setup. This account runs the platform: OxaPay keys, fees, merchants and payouts.')

@section('content')
    <form method="POST" action="{{ route('setup') }}" class="form">
        @csrf

        <div class="field">
            <label for="name">Name</label>
            <input class="input" id="name" type="text" name="name" value="{{ old('name') }}" required autofocus>
        </div>

        <div class="field">
            <label for="email">Email</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required>
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

        <button type="submit" class="btn btn-block">Create admin account</button>
    </form>
@endsection
