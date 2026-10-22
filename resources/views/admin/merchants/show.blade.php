@extends('layouts.portal')

@section('title', $merchant->business_name)
@section('subtitle', $merchant->user->name.' · '.$merchant->user->email)

@section('actions')
    <form method="POST" action="{{ route('admin.merchants.live', $merchant) }}" class="inline-form">
        @csrf
        <button type="submit" class="btn {{ $merchant->live_enabled ? 'btn-secondary' : '' }}">{{ $merchant->live_enabled ? 'Turn off live' : 'Activate live payments' }}</button>
    </form>
    <form method="POST" action="{{ route('admin.merchants.status', $merchant) }}" class="inline-form"
          onsubmit="return confirm('{{ $merchant->isActive() ? 'Suspend this merchant? Their logins and API keys stop working.' : 'Reactivate this merchant?' }}')">
        @csrf
        <button type="submit" class="btn {{ $merchant->isActive() ? 'btn-danger' : 'btn-secondary' }}">{{ $merchant->isActive() ? 'Suspend' : 'Reactivate' }}</button>
    </form>
@endsection

@section('content')
    <div class="stats">
        <div class="card stat"><div class="stat-label">Live balance</div><div class="stat-value">{{ usd($merchant->live_balance) }}</div></div>
        <div class="card stat"><div class="stat-label">Test balance</div><div class="stat-value">{{ usd($merchant->test_balance) }}</div></div>
        <div class="card stat">
            <div class="stat-label">Status</div>
            <div class="row" style="margin-top:8px">
                <span class="badge {{ $merchant->isActive() ? 'ok' : 'bad' }}">{{ ucfirst($merchant->status) }}</span>
                @if ($merchant->live_enabled)<span class="badge ok">Live</span>@elseif ($merchant->live_requested_at)<span class="badge warn">Live requested {{ $merchant->live_requested_at->diffForHumans() }}</span>@else<span class="badge">Test only</span>@endif
                @if ($merchant->user->hasVerifiedEmail())<span class="badge info">Email verified</span>@endif
            </div>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card-head"><h2>Fees for this merchant</h2></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.merchants.fees', $merchant) }}" class="form">
                    @csrf
                    @method('PUT')
                    <div class="form-row">
                        <div class="field">
                            <label for="fee_payin_percent">Pay-in %</label>
                            <input class="input" id="fee_payin_percent" name="fee_payin_percent" type="number" step="0.01" min="0" value="{{ old('fee_payin_percent', $merchant->fee_payin_percent) }}" placeholder="{{ config('platform.fees.payin_percent') }} (default)">
                        </div>
                        <div class="field">
                            <label for="fee_payin_fixed">Pay-in fixed $</label>
                            <input class="input" id="fee_payin_fixed" name="fee_payin_fixed" type="number" step="0.01" min="0" value="{{ old('fee_payin_fixed', $merchant->fee_payin_fixed) }}" placeholder="{{ config('platform.fees.payin_fixed') }} (default)">
                        </div>
                        <div class="field">
                            <label for="fee_payout_fixed">Payout fixed $</label>
                            <input class="input" id="fee_payout_fixed" name="fee_payout_fixed" type="number" step="0.01" min="0" value="{{ old('fee_payout_fixed', $merchant->fee_payout_fixed) }}" placeholder="{{ config('platform.fees.payout_fixed') }} (default)">
                        </div>
                    </div>
                    <p class="small muted mt-0">Leave a field empty to use the platform default. Now: {{ $merchant->payinFeeLabel() }} per payment, {{ usd($merchant->payoutFee()) }} per withdrawal.</p>
                    <div><button type="submit" class="btn">Save fees</button></div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Adjust balance</h2></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.merchants.adjust', $merchant) }}" class="form" onsubmit="return confirm('Apply this balance adjustment?')">
                    @csrf
                    <div class="form-row">
                        <div class="field">
                            <label for="direction">Type</label>
                            <select class="select" id="direction" name="direction">
                                <option value="credit">Credit (add)</option>
                                <option value="debit">Debit (take)</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="adjust_mode">Balance</label>
                            <select class="select" id="adjust_mode" name="mode">
                                <option value="live">Live</option>
                                <option value="test">Test</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="adjust_amount">Amount $</label>
                            <input class="input" id="adjust_amount" name="amount" type="number" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    <div class="field">
                        <label for="note">Reason (the merchant sees this)</label>
                        <input class="input" id="note" name="note" required maxlength="200" placeholder="e.g. Settled underpaid payment pay_...">
                    </div>
                    <div><button type="submit" class="btn btn-secondary">Apply adjustment</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Live payments</h2><div class="right"><a href="{{ route('admin.payments.index', ['merchant' => $merchant->id]) }}" class="btn btn-ghost btn-sm">View all</a></div></div>
        @include('admin._payments_table', ['payments' => $payments->load('merchant')])
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card-head"><h2>Withdrawals</h2></div>
            @if ($payouts->isEmpty())
                <div class="empty">No withdrawals.</div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <tbody>
                            @foreach ($payouts as $payout)
                                <tr>
                                    <td><span class="mono small">{{ Str::limit($payout->public_id, 14) }}</span> <span class="badge plain">{{ $payout->mode }}</span></td>
                                    <td><x-status :status="$payout->status" /></td>
                                    <td class="num">{{ $payout->net_amount }} {{ $payout->currency }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-head"><h2>Recent balance entries</h2></div>
            @if ($entries->isEmpty())
                <div class="empty">No entries.</div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <tbody>
                            @foreach ($entries as $entry)
                                <tr>
                                    <td>{{ $entry->label() }} <span class="badge plain">{{ $entry->mode }}</span><div class="small dim">{{ $entry->description }}</div></td>
                                    <td class="num {{ $entry->amount >= 0 ? 'pos' : 'neg' }}">{{ $entry->amount >= 0 ? '+' : '' }}{{ usd($entry->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
