@php($platform = config('platform.name'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ $platform }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}">
    <style>:root { {{ brand_vars(config('platform.brand_color')) }} }</style>
</head>
<body>
    <div class="auth">
        <div class="auth-box @yield('box_class')">
            <a href="{{ url('/') }}" class="brand">
                <span class="brand-mark">{{ Str::upper(Str::substr($platform, 0, 1)) }}</span>
                {{ $platform }}
            </a>

            <div class="card">
                <div class="card-body stack">
                    <div>
                        <h1>@yield('heading')</h1>
                        @hasSection('lead')<p class="lead">@yield('lead')</p>@endif
                    </div>

                    <x-flash />

                    @yield('content')
                </div>
            </div>

            @hasSection('footer')
                <div class="auth-foot">@yield('footer')</div>
            @endif
        </div>
    </div>
</body>
</html>
