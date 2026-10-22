@extends('layouts.portal')

@section('title', 'Balance')
@section('subtitle', 'Every amount added to or taken from your balance')

@section('actions')
    <a href="{{ route('payouts.index') }}" class="btn"><x-icon name="send" size="16" /> Withdraw</a>
@endsection

@section('content')
    <div class="stats">
        <div class="card stat hero">
            <div class="stat-label">Available balance</div>
            <div class="stat-value">{{ usd($balance) }}</div>
            <div class="stat-note">{{ ucfirst($mode) }} mode · USD</div>
        </div>
        <div class="card stat">
            <div class="stat-label">Total received</div>
            <div class="stat-value">{{ usd($received) }}</div>
            <div class="stat-note">Paid payments, before fees</div>
        </div>
        <div class="card stat">
            <div class="stat-label">Fees</div>
            <div class="stat-value">{{ usd($fees) }}</div>
            <div class="stat-note">Pay-in {{ $merchant->payinFeeLabel() }} · payout {{ usd($merchant->payoutFee()) }}</div>
        </div>
        <div class="card stat">
            <div class="stat-label">Withdrawn</div>
            <div class="stat-value">{{ usd($withdrawn) }}</div>
            <div class="stat-note">Sent to your wallets</div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Balance history</h2></div>
        @if ($entries->isEmpty())
            <div class="empty">
                <x-icon name="wallet" size="34" />
                <strong>No balance activity yet</strong>
                Paid payments and withdrawals will show up here.
            </div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Date</th><th>Type</th><th>Details</th><th class="num">Amount</th><th class="num">Balance</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr>
                                <td class="nowrap dim">{{ $entry->created_at->format('d M Y, H:i') }}</td>
                                <td class="nowrap">{{ $entry->label() }}</td>
                                <td class="dim">
                                    @if ($entry->payment)
                                        <a href="{{ route('payments.show', $entry->payment) }}" class="mono">{{ $entry->payment->public_id }}</a>
                                    @elseif ($entry->payout)
                                        <span class="mono">{{ $entry->payout->public_id }}</span>
                                    @else
                                        {{ $entry->description }}
                                    @endif
                                </td>
                                <td class="num {{ $entry->amount >= 0 ? 'pos' : 'neg' }}">{{ $entry->amount >= 0 ? '+' : '' }}{{ usd($entry->amount) }}</td>
                                <td class="num">{{ usd($entry->balance_after) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-pager :paginator="$entries" />
        @endif
    </div>
@endsection
