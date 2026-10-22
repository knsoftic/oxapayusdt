@extends('layouts.checkout')

@section('title', 'Payment')
@section('narrow', '1')

@php
    $status = $payment->status;
    $amountLabel = number_format((float) $payment->amount, 2).' '.$payment->currency;
    $tx = collect($payment->payload['txs'] ?? [])->first();
    $waitingStep = $status === 'paying' ? 2 : 1;
    $backUrl = $payment->return_url;
    $retryUrl = $payment->paymentLink?->active ? $payment->paymentLink->url() : null;
@endphp

@section('content')
    <div class="co-card" id="payment"
         data-status="{{ $status }}"
         data-final="{{ $payment->isFinal() ? 1 : 0 }}"
         data-status-url="{{ route('checkout.status', $payment) }}">

        @if ($payment->isPaid())
            <div class="result">
                <div class="result-icon ok">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                </div>
                <h1>Payment received</h1>
                <p>Thank you! Your payment of {{ $amountLabel }} to {{ $merchant->business_name }} has been confirmed.</p>

                <dl class="details">
                    <div><dt>Amount</dt><dd>{{ $amountLabel }}</dd></div>
                    @if ($payment->pay_amount)
                        <div><dt>Paid with</dt><dd>{{ $payment->pay_amount }} {{ $payment->pay_currency }}</dd></div>
                    @elseif ($tx)
                        <div><dt>Paid with</dt><dd>{{ $tx['amount'] ?? $tx['received_amount'] ?? '' }} {{ $tx['currency'] ?? '' }}</dd></div>
                    @endif
                    @if ($payment->network ?? ($tx['network'] ?? null))
                        <div><dt>Network</dt><dd>{{ $payment->network ?? $tx['network'] }}</dd></div>
                    @endif
                    @if (! empty($tx['tx_hash']))
                        <div><dt>Transaction</dt><dd><code>{{ Str::limit($tx['tx_hash'], 22) }}</code></dd></div>
                    @endif
                    <div><dt>Date</dt><dd>{{ $payment->paid_at?->format('d M Y, h:i A T') }}</dd></div>
                </dl>

                @if ($backUrl)
                    <a href="{{ $backUrl }}" class="btn">Return to {{ $merchant->business_name }}</a>
                @endif
            </div>

        @elseif ($status === 'expired')
            <div class="result">
                <div class="result-icon warn">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </div>
                <h1>Payment window expired</h1>
                <p>This payment wasn't completed in time. If you already sent crypto, contact {{ $merchant->business_name }} with the payment ID below before paying again.</p>
                @if ($retryUrl)
                    <a href="{{ $retryUrl }}" class="btn">Start a new payment</a>
                @elseif ($backUrl)
                    <a href="{{ $backUrl }}" class="btn btn-ghost">Return to {{ $merchant->business_name }}</a>
                @endif
            </div>

        @elseif ($status === 'underpaid')
            <div class="result">
                <div class="result-icon warn">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                </div>
                <h1>Partial payment received</h1>
                <p>We received less than the required amount. Please contact {{ $merchant->business_name }} with the payment ID below so they can sort it out.</p>
            </div>

        @elseif (in_array($status, ['refunding', 'refunded'], true))
            <div class="result">
                <div class="result-icon live">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>
                </div>
                <h1>{{ $status === 'refunded' ? 'Payment refunded' : 'Refund in progress' }}</h1>
                <p>This payment {{ $status === 'refunded' ? 'has been' : 'is being' }} refunded.</p>
            </div>

        @elseif ($status === 'failed')
            <div class="result">
                <div class="result-icon bad">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M7 7l10 10M17 7L7 17"/></svg>
                </div>
                <h1>Payment couldn't be started</h1>
                <p>Something went wrong while creating this payment. Nothing was charged.</p>
                @if ($retryUrl)<a href="{{ $retryUrl }}" class="btn">Try again</a>@endif
            </div>

        @elseif ($payment->isWhiteLabel())
            <div class="pay-head">
                @if ($status === 'paying')
                    <span class="badge badge-live"><span class="dot"></span> Confirming payment</span>
                @else
                    <span class="badge badge-wait"><span class="dot"></span> Awaiting payment</span>
                @endif

                @if ($payment->expires_at && $status !== 'paying')
                    <span class="timer" id="timer" data-expires="{{ $payment->expires_at->timestamp }}" title="Time left to pay">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2M9 2h6"/></svg>
                        <span id="timer-text">--:--</span>
                    </span>
                @endif
            </div>

            @if ($status === 'paying')
                <div class="result">
                    <div class="result-icon live"><span class="spinner" style="width:30px;height:30px;border-width:3px"></span></div>
                    <h1>Payment detected</h1>
                    <p>We've seen your {{ $payment->pay_currency }} transaction and are waiting for network confirmations. This usually takes a few minutes.</p>
                </div>
            @else
                <div class="send-label">Send exactly</div>
                <div class="send-amount">
                    <strong>{{ $payment->pay_amount }} {{ $payment->pay_currency }}</strong>
                    <button type="button" class="copy" data-copy="{{ $payment->pay_amount }}">Copy</button>
                </div>
                <div class="send-sub">≈ {{ $amountLabel }}</div>

                <div class="pay-body">
                    @if ($payment->qr_code)
                        <div class="qr"><img src="{{ $payment->qr_code }}" alt="QR code for the {{ $payment->pay_currency }} payment address" width="156" height="156"></div>
                    @endif

                    <div class="pay-fields">
                        <div class="kv">
                            <div class="kv-label">Network</div>
                            <div class="net-tag">{{ $payment->network }}</div>
                        </div>

                        <div class="kv">
                            <div class="kv-label">{{ $payment->pay_currency }} address</div>
                            <div class="mono-box">
                                <code>{{ $payment->address }}</code>
                                <button type="button" class="copy" data-copy="{{ $payment->address }}">Copy</button>
                            </div>
                        </div>

                        @if ($payment->memo)
                            <div class="kv">
                                <div class="kv-label">Memo / tag (required)</div>
                                <div class="mono-box">
                                    <code>{{ $payment->memo }}</code>
                                    <button type="button" class="copy" data-copy="{{ $payment->memo }}">Copy</button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="note">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                    <span>Send only <strong>{{ $payment->pay_currency }}</strong> on <strong>{{ $payment->network }}</strong>. Other coins, other networks, or payments after the timer ends can be lost.</span>
                </div>
                @if ($payment->memo)
                    <div class="note">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                        <span>Include the memo / tag above, or the payment can't be matched to your order.</span>
                    </div>
                @endif
            @endif

            <div class="steps" aria-hidden="true">
                <div class="step {{ $waitingStep > 1 ? 'done' : 'active' }}">Awaiting payment</div>
                <div class="step {{ $waitingStep === 2 ? 'active' : '' }}">Confirming</div>
                <div class="step">Completed</div>
            </div>

            <div class="live-note"><span class="spinner"></span> This page updates automatically</div>

        @else
            {{-- Hosted OxaPay invoice that isn't finished yet. --}}
            <div class="result">
                <div class="result-icon live">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h3"/></svg>
                </div>
                <h1>{{ $status === 'paying' ? 'Payment detected' : 'Complete your payment' }}</h1>
                <p>
                    @if ($status === 'paying')
                        We're waiting for network confirmations. This usually takes a few minutes.
                    @else
                        Finish paying {{ $amountLabel }} on the secure OxaPay page.
                    @endif
                </p>
                @if ($payment->payment_url && $status !== 'paying')
                    <a href="{{ $payment->payment_url }}" class="btn">Continue to payment</a>
                @endif
            </div>
            <div class="live-note"><span class="spinner"></span> This page updates automatically</div>
        @endif
    </div>

    <p class="co-ref">Payment {{ $payment->public_id }}</p>
@endsection

@push('scripts')
    <script>
        (() => {
            const card = document.getElementById('payment');

            document.querySelectorAll('[data-copy]').forEach((button) => {
                button.addEventListener('click', async () => {
                    try {
                        await navigator.clipboard.writeText(button.dataset.copy);
                    } catch {
                        const field = Object.assign(document.createElement('textarea'), { value: button.dataset.copy });
                        document.body.append(field);
                        field.select();
                        document.execCommand('copy');
                        field.remove();
                    }
                    button.textContent = 'Copied';
                    button.classList.add('copied');
                    setTimeout(() => { button.textContent = 'Copy'; button.classList.remove('copied'); }, 1600);
                });
            });

            if (card.dataset.final === '1') return;

            // Reload when OxaPay reports a new status (paying, paid, expired, ...).
            const check = async () => {
                try {
                    const response = await fetch(card.dataset.statusUrl, { headers: { Accept: 'application/json' } });
                    if (!response.ok) return;
                    const data = await response.json();
                    if (data.status !== card.dataset.status) location.reload();
                } catch {
                    // Network hiccup: try again on the next tick.
                }
            };
            setInterval(check, 8000);

            const timer = document.getElementById('timer');
            if (timer) {
                const text = document.getElementById('timer-text');
                const expires = Number(timer.dataset.expires) * 1000;
                let checkedAtZero = false;
                const tick = () => {
                    const left = Math.max(0, Math.floor((expires - Date.now()) / 1000));
                    const h = Math.floor(left / 3600), m = Math.floor((left % 3600) / 60), s = left % 60;
                    const pad = (n) => String(n).padStart(2, '0');
                    text.textContent = h ? `${h}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`;
                    timer.classList.toggle('low', left < 300);
                    if (left === 0 && !checkedAtZero) { checkedAtZero = true; setTimeout(check, 3000); }
                };
                tick();
                setInterval(tick, 1000);
            }
        })();
    </script>
@endpush
