@extends('layouts.portal')

@section('title', 'Withdrawals')
@section('subtitle', 'Send your balance to your own wallet as '.config('platform.payout_currency'))

@section('content')
    <div class="grid grid-main">
        <div class="card">
            <div class="card-head"><h2>History</h2></div>
            @if ($payouts->isEmpty())
                <div class="empty">
                    <x-icon name="send" size="34" />
                    <strong>No withdrawals yet</strong>
                    Your withdrawals will be listed here.
                </div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Withdrawal</th><th>Status</th><th class="num">Amount</th><th class="num">You receive</th><th>To</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($payouts as $payout)
                                <tr>
                                    <td>
                                        <span class="mono">{{ Str::limit($payout->public_id, 16) }}</span>
                                        <div class="small dim">{{ $payout->created_at->format('d M Y, H:i') }}</div>
                                    </td>
                                    <td>
                                        <x-status :status="$payout->status" />
                                        @if ($payout->note && in_array($payout->status, ['rejected', 'failed']))
                                            <div class="small dim" style="max-width:200px">{{ $payout->note }}</div>
                                        @endif
                                    </td>
                                    <td class="num">{{ usd($payout->amount) }}</td>
                                    <td class="num"><strong>{{ $payout->net_amount }} {{ $payout->currency }}</strong></td>
                                    <td class="small">
                                        <span class="mono">{{ Str::limit($payout->address, 14) }}</span>
                                        <div class="dim">{{ $payout->networkLabel() }}</div>
                                        @if ($payout->tx_hash)<div class="dim mono" title="{{ $payout->tx_hash }}">tx {{ Str::limit($payout->tx_hash, 12) }}</div>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-pager :paginator="$payouts" />
            @endif
        </div>

        <div class="card">
            <div class="card-head"><h2>Request a withdrawal</h2></div>
            <div class="card-body">
                <div class="alert alert-info" style="margin-bottom:16px">
                    <x-icon name="wallet" />
                    <div>
                        <p><strong>{{ usd($balance) }}</strong> available</p>
                        <p class="small">Fee {{ $merchant->payoutFeeLabel() }} per withdrawal · minimum {{ usd(config('platform.min_payout')) }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('payouts.store') }}" class="form" id="payout-form">
                    @csrf
                    <div class="field">
                        <label for="amount">Amount (USD)</label>
                        <div class="input-group">
                            <input class="input" id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" required>
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('amount').value='{{ $balance }}'; document.getElementById('amount').dispatchEvent(new Event('input'))">Max</button>
                        </div>
                        <div class="hint" id="receive-hint">You receive the amount minus the {{ $merchant->payoutFeeLabel() }} fee.</div>
                    </div>
                    <div class="field">
                        <label for="network">Network</label>
                        <select class="select" id="network" name="network" required>
                            @foreach ($networks as $value => $label)
                                <option value="{{ $value }}" @selected(old('network', 'Tron') === $value)>{{ config('platform.payout_currency') }} · {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="address">Your wallet address</label>
                        <input class="input mono" id="address" name="address" value="{{ old('address') }}" required maxlength="120" autocomplete="off" spellcheck="false">
                        <div class="hint">Double-check it. Crypto sent to a wrong address or network can't be recovered.</div>
                    </div>
                    <div class="field">
                        <label for="memo">Memo / tag <span class="muted">(only if your wallet needs one)</span></label>
                        <input class="input" id="memo" name="memo" value="{{ old('memo') }}" maxlength="100">
                    </div>
                    <button type="submit" class="btn">{{ $mode === 'test' ? 'Withdraw (test)' : 'Request withdrawal' }}</button>
                    @if ($mode === 'test')
                        <p class="small muted mt-0">Test withdrawals complete instantly and send nothing.</p>
                    @else
                        <p class="small muted mt-0">Withdrawals are reviewed before they are sent, usually within one business day.</p>
                    @endif
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const amount = document.getElementById('amount');
            const hint = document.getElementById('receive-hint');
            const percent = {{ $merchant->payoutFeePercent() }}, fixed = {{ $merchant->payoutFeeFixed() }}, label = @json($merchant->payoutFeeLabel());
            amount.addEventListener('input', () => {
                const value = parseFloat(amount.value);
                // Same rule as the server: amount x % + fixed, in cents.
                const fee = isNaN(value) ? 0 : Math.round(value * percent + fixed * 100) / 100;
                hint.textContent = value > fee
                    ? `You receive ${(value - fee).toFixed(2)} {{ config('platform.payout_currency') }} after the $${fee.toFixed(2)} fee.`
                    : `You receive the amount minus the ${label} fee.`;
            });
        })();
    </script>
@endpush
