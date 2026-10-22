@extends('layouts.auth')

@section('title', 'Log in')
@section('heading', 'Welcome back')
@section('lead', 'Log in to your account.')

@section('content')
    <form method="POST" action="{{ route('login') }}" class="form">
        @csrf

        <div class="field">
            <label for="email">Email</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </div>

        <div class="field">
            <div class="row" style="justify-content:space-between">
                <label for="password" style="margin:0">Password</label>
                <a href="{{ route('password.request') }}" class="small">Forgot password?</a>
            </div>
            <input class="input" id="password" type="password" name="password" required autocomplete="current-password" style="margin-top:6px">
        </div>

        <label class="check"><input type="checkbox" name="remember" value="1"> Keep me logged in</label>

        <button type="submit" class="btn btn-block">Log in</button>
    </form>
@endsection

@section('footer')
    New here? <a href="{{ route('register') }}">Create a merchant account</a>
@endsection
