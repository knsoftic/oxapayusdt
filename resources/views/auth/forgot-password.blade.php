@extends('layouts.auth')

@section('title', 'Forgot password')
@section('heading', 'Reset your password')
@section('lead', "Enter your email and we'll send you a reset link.")

@section('content')
    <form method="POST" action="{{ route('password.email') }}" class="form">
        @csrf

        <div class="field">
            <label for="email">Email</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>

        <button type="submit" class="btn btn-block">Send reset link</button>
    </form>
@endsection

@section('footer')
    <a href="{{ route('login') }}">Back to log in</a>
@endsection
