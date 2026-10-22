@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin();
    $platform = config('platform.name');

    $nav = $isAdmin
        ? [
            ['admin.dashboard', 'Overview', 'chart', 'admin.dashboard'],
            ['admin.merchants.index', 'Merchants', 'users', 'admin.merchants.*', \App\Models\Merchant::where('live_enabled', false)->whereNotNull('live_requested_at')->count()],
            ['admin.payments.index', 'Payments', 'payments', 'admin.payments.*'],
            ['admin.payouts.index', 'Payouts', 'send', 'admin.payouts.*', \App\Models\Payout::where('status', 'pending')->where('mode', 'live')->count()],
            ['admin.settings.edit', 'Settings', 'settings', 'admin.settings.*'],
        ]
        : [
            ['dashboard', 'Home', 'home', 'dashboard'],
            ['payments.index', 'Payments', 'payments', 'payments.*'],
            ['links.index', 'Payment links', 'link', 'links.*'],
            ['balance.index', 'Balance', 'wallet', 'balance.*'],
            ['payouts.index', 'Withdrawals', 'send', 'payouts.*'],
            ['developers.index', 'Developers', 'code', 'developers.*'],
            ['settings.edit', 'Settings', 'settings', 'settings.*'],
        ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ $platform }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}">
    <style>:root { {{ brand_vars(config('platform.brand_color')) }} }</style>
</head>
<body>
    <div class="shell" id="shell">
        <aside class="sidebar">
            <a href="{{ $user->homeRoute() }}" class="brand">
                <span class="brand-mark">{{ Str::upper(Str::substr($platform, 0, 1)) }}</span>
                <span>{{ $platform }}<small>{{ $isAdmin ? 'Admin' : $merchant->business_name }}</small></span>
            </a>

            @unless ($isAdmin)
                <form method="POST" action="{{ route('mode.switch') }}" class="mode-switch" aria-label="Data mode">
                    @csrf
                    <button type="submit" name="mode" value="test" class="{{ $mode === 'test' ? 'on test' : '' }}">Test</button>
                    <button type="submit" name="mode" value="live" class="{{ $mode === 'live' ? 'on' : '' }}">Live</button>
                </form>
            @endunless

            <nav class="nav">
                @foreach ($nav as $item)
                    <a href="{{ route($item[0]) }}" class="{{ request()->routeIs($item[3]) ? 'active' : '' }}">
                        <x-icon :name="$item[2]" />
                        {{ $item[1] }}
                        @if (! empty($item[4]))
                            <span class="count">{{ $item[4] }}</span>
                        @endif
                    </a>
                @endforeach

                <div class="nav-label">Resources</div>
                <a href="{{ route('docs') }}" target="_blank" rel="noopener"><x-icon name="book" /> API docs</a>
            </nav>

            <div class="sidebar-foot">
                <div class="who">{{ $user->name }}<small>{{ $user->email }}</small></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="linkish">Log out</button>
                </form>
            </div>
        </aside>

        <div class="main">
            @unless ($isAdmin)
                @if ($mode === 'test')
                    <div class="test-banner"><x-icon name="flask" size="15" /> Test mode: payments use the OxaPay sandbox and no real money moves.</div>
                @elseif (! $merchant->live_enabled)
                    <div class="test-banner"><x-icon name="lock" size="15" /> Live mode isn't activated yet. <a href="{{ route('settings.edit') }}#live">Request activation</a></div>
                @endif
            @endunless

            <header class="topbar">
                <button type="button" class="menu-btn" aria-label="Open menu" onclick="document.getElementById('shell').classList.toggle('nav-open')"><x-icon name="menu" /></button>
                <div>
                    <h1>@yield('title')</h1>
                    @hasSection('subtitle')<div class="sub">@yield('subtitle')</div>@endif
                </div>
                <div class="actions">@yield('actions')</div>
            </header>

            <main class="content">
                @if (! $isAdmin && ! $user->hasVerifiedEmail())
                    <div class="alert alert-info">
                        <x-icon name="info" />
                        <p>Please verify your email address. We sent a link to {{ $user->email }}.</p>
                        <form method="POST" action="{{ route('verification.send') }}" class="inline-form" style="margin-left:auto">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm">Resend link</button>
                        </form>
                    </div>
                @endif

                <x-flash />

                @yield('content')
            </main>
        </div>
    </div>

    <script>
        // Copy buttons: <button class="copy" data-copy="text">Copy</button>
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-copy]');
            if (!button) return;
            try {
                await navigator.clipboard.writeText(button.dataset.copy);
            } catch {
                const field = Object.assign(document.createElement('textarea'), { value: button.dataset.copy });
                document.body.append(field);
                field.select();
                document.execCommand('copy');
                field.remove();
            }
            const label = button.textContent;
            button.textContent = 'Copied';
            button.classList.add('copied');
            setTimeout(() => { button.textContent = label; button.classList.remove('copied'); }, 1500);
        });

        document.getElementById('shell').addEventListener('click', (event) => {
            if (event.target.id === 'shell') event.currentTarget.classList.remove('nav-open');
        });
    </script>
    @stack('scripts')
</body>
</html>
