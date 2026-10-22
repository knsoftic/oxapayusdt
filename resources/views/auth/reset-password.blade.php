@extends('layouts.auth')

@section('title', 'Choose a new password')
@section('heading', 'Choose a new password')

@section('content')
    <form method="POST" action="{{ route('password.update') }}" class="form">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="field">
            <label for="email">Email</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email', $email) }}" required>
        </div>

        <div class="field">
            <label for="password">New password</label>
            <input class="input" id="password" type="password" name="password" required autofocus autocomplete="new-password">
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm new password</label>
            <input class="input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-block">Reset password</button>
    </form>
@endsection
