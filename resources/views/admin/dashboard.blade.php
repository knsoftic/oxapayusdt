@extends('layouts.portal')

@section('title', 'Overview')
@section('subtitle', 'Live payments across all merchants')

@section('content')
    @unless ($oxapayConnected)
        <div class="alert alert-warn"><x-icon name="alert" /><p>OxaPay is not connected yet. Add your merchant API key in <a href="{{ route('admin.settings.edit') }}">Settings</a>.</p></div>
    @endunless

    <div class="stats">
        <div class="card stat hero">
            <div class="stat-label">Fees earned</div>
            <div class="stat-value">{{ usd($feesEarned) }}</div>
            <div class="stat-note">Pay-in and payout fees, all time</div>
        </div>
        <div class="card stat">
            <div class="stat-label">Paid volume</div>
            <div class="stat-value">{{ usd($volume) }}</div>
            <div class="stat-note">{{ usd($volume30) }} in the last 30 days</div>
        </div>
        <div class="card stat">
            <div class="stat-label">Owed to merchants</div>
            <div class="stat-value">{{ usd($owed) }}</div>
            <div class="stat-note">Sum of live balances</div>
        </div>
        <div class="card stat">
            <div class="stat-label">Merchants</div>
            <div class="stat-value">{{ $merchantCount }}</div>
            <div class="stat-note">{{ $liveCount }} live</div>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card-head">
                <h2>Payouts waiting for you</h2>
                <div class="right"><span class="sub">{{ usd($pendingPayoutTotal) }} to send</span></div>
            </div>
            @if ($pendingPayouts->isEmpty())
                <div class="empty">Nothing to review.</div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <tbody>
                            @foreach ($pendingPayouts as $payout)
                                <tr>
                                    <td><a href="{{ route('admin.merchants.show', $payout->merchant) }}">{{ $payout->merchant->business_name }}</a><div class="small dim">{{ $payout->created_at->diffForHumans() }}</div></td>
                                    <td class="num"><strong>{{ $payout->net_amount }} {{ $payout->currency }}</strong><div class="small dim">{{ $payout->networkLabel() }}</div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-foot"><a href="{{ route('admin.payouts.index') }}" class="btn btn-sm">Review payouts</a></div>
            @endif
        </div>

        <div class="card">
            <div class="card-head"><h2>Live access requests</h2></div>
            @if ($liveRequests->isEmpty())
                <div class="empty">No open requests.</div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <tbody>
                            @foreach ($liveRequests as $request)
                                <tr>
                                    <td><strong>{{ $request->business_name }}</strong><div class="small dim">{{ $request->user->email }}</div></td>
                                    <td class="dim nowrap">{{ $request->live_requested_at->diffForHumans() }}</td>
                                    <td class="right"><a href="{{ route('admin.merchants.show', $request) }}" class="btn btn-secondary btn-sm">Review</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2>Latest live payments</h2>
            <div class="right"><a href="{{ route('admin.payments.index') }}" class="btn btn-ghost btn-sm">View all</a></div>
        </div>
        @include('admin._payments_table', ['payments' => $recentPayments])
    </div>
@endsection
