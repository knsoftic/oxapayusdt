@extends('layouts.checkout')

@section('title', 'Checkout')

@php
    // Icons from the cryptocurrency-icons package; coins without one get a letter badge.
    $iconNames = ['BTC' => 'btc', 'ETH' => 'eth', 'USDT' => 'usdt', 'USDC' => 'usdc', 'BNB' => 'bnb', 'DOGE' => 'doge', 'POL' => 'matic', 'LTC' => 'ltc', 'SOL' => 'sol', 'TRX' => 'trx', 'XMR' => 'xmr', 'DAI' => 'dai', 'BCH' => 'bch', 'XRP' => 'xrp'];
    $selectedCoin = old('pay_currency', isset($currencies['USDT']) ? 'USDT' : array_key_first($currencies));
@endphp

@section('content')
    <form method="POST" action="{{ route('checkout.start', $payment) }}" id="checkout">
        @csrf

        @if ($errors->any())
            <div class="alert" role="alert">
                <div>
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="co-grid {{ $hosted ? 'single' : '' }}">
            <section class="co-card">
                <h2 class="co-title">@unless ($hosted)<span class="co-step">1</span>@endunless Order summary</h2>

                <div class="co-summary">
                    <span class="label">Paying</span>
                    <span class="what">{{ $merchant->business_name }}</span>
                    @if ($payment->description)
                        <span class="label" style="margin-top:10px">For</span>
                        <span>{{ $payment->description }}</span>
                    @endif
                    @if ($payment->reference)
                        <span class="label" style="margin-top:10px">Reference</span>
                        <span>{{ $payment->reference }}</span>
                    @endif
                </div>

                <div class="summary-total">
                    <span>Total</span>
                    <strong>{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</strong>
                </div>

                @if ($hosted)
                    <button type="submit" class="btn">
                        Pay with crypto
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </button>
                    <p class="muted" style="text-align:center;margin:12px 0 0">
                        @if ($payment->isTest())
                            You'll pay on OxaPay's test page, where you can simulate the payment.
                        @else
                            You'll choose a coin on OxaPay's secure page.
                        @endif
                    </p>
                @endif
            </section>

            @unless ($hosted)
                <section class="co-card">
                    <h2 class="co-title"><span class="co-step">2</span> Choose how to pay</h2>

                    @if (empty($currencies))
                        <p class="muted">Crypto payment options are unavailable right now. Please try again in a few minutes.</p>
                    @else
                        <div class="coins" role="radiogroup" aria-label="Coin">
                            @foreach ($currencies as $coin)
                                <div class="choice">
                                    <input type="radio" name="pay_currency" id="coin-{{ $coin['symbol'] }}" value="{{ $coin['symbol'] }}" @checked($selectedCoin === $coin['symbol']) required>
                                    <label class="coin-label" for="coin-{{ $coin['symbol'] }}">
                                        <span class="coin-icon">
                                            @isset($iconNames[$coin['symbol']])
                                                <img src="https://cdn.jsdelivr.net/npm/cryptocurrency-icons@0.18.1/svg/color/{{ $iconNames[$coin['symbol']] }}.svg" alt="" loading="lazy"
                                                     onerror="this.replaceWith(document.createTextNode('{{ Str::substr($coin['symbol'], 0, 2) }}'))">
                                            @else
                                                {{ Str::substr($coin['symbol'], 0, 2) }}
                                            @endisset
                                        </span>
                                        <span class="coin-text">
                                            <span class="coin-sym">{{ $coin['symbol'] }}</span>
                                            <span class="coin-name">{{ $coin['name'] }}</span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        @foreach ($currencies as $coin)
                            @if (count($coin['networks']) > 1)
                                <fieldset class="networks" data-coin="{{ $coin['symbol'] }}" @if ($selectedCoin !== $coin['symbol']) hidden @endif>
                                    <legend>Network for {{ $coin['symbol'] }}</legend>
                                    <div class="pills">
                                        @foreach ($coin['networks'] as $key => $network)
                                            <div class="choice">
                                                <input type="radio" name="network" id="net-{{ $coin['symbol'] }}-{{ $loop->index }}" value="{{ $key }}"
                                                       @checked($selectedCoin === $coin['symbol'] && old('network') === $key)
                                                       @disabled($selectedCoin !== $coin['symbol'])>
                                                <label class="pill-label" for="net-{{ $coin['symbol'] }}-{{ $loop->index }}">
                                                    {{ $network['name'] }}
                                                    @if ($network['standard'])<small>{{ $network['standard'] }}</small>@endif
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endif
                        @endforeach

                        <button type="submit" class="btn">
                            Continue to payment
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </button>
                    @endif
                </section>
            @endunless
        </div>
    </form>

    <p class="co-ref">Payment {{ $payment->public_id }}</p>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('checkout');

            // Show only the networks of the selected coin, and require a choice there.
            const syncNetworks = () => {
                const coin = form.querySelector('input[name="pay_currency"]:checked')?.value;
                form.querySelectorAll('.networks').forEach((group) => {
                    const active = group.dataset.coin === coin;
                    group.hidden = !active;
                    group.querySelectorAll('input').forEach((input) => {
                        input.disabled = !active;
                        input.required = active;
                    });
                });
            };

            form.addEventListener('change', (event) => {
                if (event.target.name === 'pay_currency') syncNetworks();
            });
            syncNetworks();

            // Avoid double submits while OxaPay creates the payment.
            form.addEventListener('submit', () => {
                form.querySelectorAll('button[type="submit"]').forEach((b) => { b.disabled = true; b.style.opacity = .7; });
            });
        })();
    </script>
@endpush
