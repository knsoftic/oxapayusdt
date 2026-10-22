@php
    // Customer-facing pages carry the merchant's brand, not the platform's.
    $storeName = $merchant->business_name;
    $testMode = isset($payment) ? $payment->isTest() : (isset($link) && $link->mode === 'test');
    $narrow = trim($__env->yieldContent('narrow')) !== '';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ $storeName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/checkout.css') }}">
    <style>:root { {{ brand_vars($merchant->brand_color ?: config('platform.brand_color')) }} }</style>
</head>
<body>
    @if ($testMode)
        <div class="co-test">Test mode · no real money is charged</div>
    @endif

    <header class="co-header {{ $narrow ? 'narrow' : '' }}">
        <div class="co-logo" aria-hidden="true">{{ Str::upper(Str::substr($storeName, 0, 1)) }}</div>
        <div class="co-store">{{ $storeName }}</div>
        <div class="co-secure">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            Secure crypto checkout
        </div>
    </header>

    <main class="co-main {{ $narrow ? 'narrow' : '' }}">
        @yield('content')

        <p class="co-footer">Payments processed by {{ config('platform.name') }}</p>
    </main>

    @stack('scripts')
</body>
</html>
