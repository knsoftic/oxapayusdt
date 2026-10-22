@extends('layouts.portal')

@section('title', 'Home')
@section('subtitle', $merchant->business_name.' · '.($mode === 'test' ? 'Test data' : 'Live data'))

@section('actions')
    <a href="{{ route('links.index') }}" class="btn btn-secondary"><x-icon name="link" size="16" /> New payment link</a>
    <a href="{{ route('developers.index') }}" class="btn"><x-icon name="code" size="16" /> API keys</a>
@endsection

@section('content')
    <div class="stats">
        <div class="card stat hero">
            <div class="stat-label"><x-icon name="wallet" size="15" /> Available balance</div>
            <div class="stat-value">{{ usd($balance) }}</div>
            <div class="stat-note">After fees · USD</div>
            <a href="{{ route('payouts.index') }}" class="btn btn-sm">Withdraw</a>
        </div>
        <div class="card stat">
            <div class="stat-label">Received today</div>
            <div class="stat-value">{{ usd($volumeToday) }}</div>
            <div class="stat-note">Gross, before fees</div>
        </div>
        <div class="card stat">
            <div class="stat-label">Last 30 days</div>
            <div class="stat-value">{{ usd($volume30) }}</div>
            <div class="stat-note">{{ $paidCount30 }} paid {{ Str::plural('payment', $paidCount30) }}</div>
        </div>
        <div class="card stat">
            <div class="stat-label">Pending withdrawals</div>
            <div class="stat-value">{{ usd($pendingPayouts) }}</div>
            <div class="stat-note">Waiting for review or confirmation</div>
        </div>
    </div>

    <div class="grid grid-main">
        <div class="card">
            <div class="card-head">
                <h2>Paid volume</h2>
                <span class="sub">Last 14 days</span>
            </div>
            <div class="card-body">
                <div class="bars" role="img" aria-label="Paid volume per day for the last 14 days">
                    @foreach ($chart as $day)
                        <div class="bar" title="{{ $day['date']->format('d M') }}: {{ usd($day['amount']) }}">
                            <span style="height: {{ max(2, round($day['amount'] / $chartMax * 100)) }}%"></span>
                        </div>
                    @endforeach
                </div>
                <div class="bar-labels">
                    @foreach ($chart as $day)
                        <span>{{ $loop->first || $loop->last || $loop->index % 3 === 0 ? $day['date']->format('d/m') : '' }}</span>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($mode === 'test' || ! $merchant->live_enabled)
            <div class="card">
                <div class="card-head"><h2>Get started</h2></div>
                <div class="card-body">
                    <ul class="steps-list">
                        <li class="{{ $hasKey ? 'done' : '' }}">
                            <span class="tick"><x-icon name="check" size="13" /></span>
                            <span><a href="{{ route('developers.index') }}" class="what">Create your test API key</a><span class="how">Used to create payments from your website.</span></span>
                        </li>
                        <li class="{{ $hasPayment ? 'done' : '' }}">
                            <span class="tick"><x-icon name="check" size="13" /></span>
                            <span><a href="{{ route('links.index') }}" class="what">Make a test payment</a><span class="how">Use a payment link or the API, then simulate paying it.</span></span>
                        </li>
                        <li class="{{ $merchant->webhook_url ? 'done' : '' }}">
                            <span class="tick"><x-icon name="check" size="13" /></span>
                            <span><a href="{{ route('developers.index') }}#webhook" class="what">Add a webhook URL</a><span class="how">Get told the moment a payment is paid.</span></span>
                        </li>
                        <li class="{{ $merchant->live_enabled ? 'done' : '' }}">
                            <span class="tick"><x-icon name="check" size="13" /></span>
                            <span><a href="{{ route('settings.edit') }}#live" class="what">Activate live payments</a><span class="how">{{ $merchant->live_requested_at ? 'Requested, under review.' : 'Request a review when you are ready.' }}</span></span>
                        </li>
                    </ul>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-head"><h2>Your fees</h2></div>
                <div class="card-body">
                    <dl class="dl">
                        <dt>Per payment</dt><dd>{{ $merchant->payinFeeLabel() }}</dd>
                        <dt>Per withdrawal</dt><dd>{{ usd($merchant->payoutFee()) }}</dd>
                        <dt>Settlement</dt><dd>USD balance, withdrawn as USDT</dd>
                    </dl>
                </div>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-head">
            <h2>Recent payments</h2>
            <div class="right"><a href="{{ route('payments.index') }}" class="btn btn-ghost btn-sm">View all</a></div>
        </div>
        @include('merchant.payments._table', ['payments' => $recentPayments])
    </div>
@endsection
