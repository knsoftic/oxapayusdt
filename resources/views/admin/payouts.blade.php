@extends('layouts.portal')

@section('title', 'Payouts')
@section('subtitle', 'Review merchant withdrawals and send them')

@section('content')
    @unless (filled(config('oxapay.payout_api_key')))
        <div class="alert alert-warn">
            <x-icon name="alert" />
            <p>No OxaPay payout key is set, so "Send via OxaPay" won't work. Add it in <a href="{{ route('admin.settings.edit') }}">Settings</a>, or pay from your own wallet and use "Mark as paid".</p>
        </div>
    @endunless

    <div class="card">
        <div class="card-head">
            <div class="row">
                @foreach (['pending' => 'Waiting', 'processing' => 'Processing', 'completed' => 'Completed', 'rejected' => 'Rejected', 'failed' => 'Failed', 'all' => 'All'] as $value => $label)
                    <a href="{{ route('admin.payouts.index', ['status' => $value]) }}" class="btn btn-sm {{ $status === $value ? '' : 'btn-ghost' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        @if ($payouts->isEmpty())
            <div class="empty"><x-icon name="send" size="34" /><strong>Nothing here</strong>No {{ $status === 'all' ? '' : $status }} payouts.</div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Merchant</th><th>Send</th><th>To</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($payouts as $payout)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.merchants.show', $payout->merchant) }}" class="row-link">{{ $payout->merchant->business_name }}</a>
                                    <div class="small dim"><span class="mono">{{ Str::limit($payout->public_id, 16) }}</span> · {{ $payout->created_at->diffForHumans() }}</div>
                                    @if ($payout->mode === 'test')<span class="badge warn">Test</span>@endif
                                </td>
                                <td class="nowrap">
                                    <strong>{{ $payout->net_amount }} {{ $payout->currency }}</strong>
                                    <div class="small dim">{{ usd($payout->amount) }} − {{ usd($payout->fee) }} fee</div>
                                </td>
                                <td>
                                    <div class="secret" style="padding:5px 6px 5px 10px">
                                        <code class="small">{{ $payout->address }}</code>
                                        <button type="button" class="copy" data-copy="{{ $payout->address }}">Copy</button>
                                    </div>
                                    <div class="small dim" style="margin-top:4px">{{ $payout->networkLabel() }}@if ($payout->memo) · memo <code>{{ $payout->memo }}</code>@endif</div>
                                    @if ($payout->tx_hash)<div class="small dim mono" title="{{ $payout->tx_hash }}">tx {{ Str::limit($payout->tx_hash, 20) }}</div>@endif
                                </td>
                                <td>
                                    <x-status :status="$payout->status" />
                                    @if ($payout->note)<div class="small dim" style="max-width:220px">{{ $payout->note }}</div>@endif
                                    @if ($payout->processedBy)<div class="small dim">by {{ $payout->processedBy->name }}</div>@endif
                                </td>
                                <td>
                                    @if ($payout->mode === 'live' && in_array($payout->status, ['pending', 'processing']))
                                        <div class="stack" style="gap:6px;min-width:220px">
                                            @if ($payout->status === 'pending')
                                                <form method="POST" action="{{ route('admin.payouts.approve', $payout) }}" class="inline-form"
                                                      onsubmit="return confirm('Send {{ $payout->net_amount }} {{ $payout->currency }} on {{ $payout->networkLabel() }} to {{ $payout->address }} through OxaPay?')">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-block" @disabled(! filled(config('oxapay.payout_api_key')))>Send via OxaPay</button>
                                                </form>
                                            @endif
                                            <details class="inline">
                                                <summary>Mark as paid</summary>
                                                <form method="POST" action="{{ route('admin.payouts.paid', $payout) }}" class="row">
                                                    @csrf
                                                    <input class="input" name="tx_hash" placeholder="Transaction hash" style="flex:1;min-width:0">
                                                    <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                                                </form>
                                            </details>
                                            <details class="inline">
                                                <summary style="color:var(--bad)">Reject</summary>
                                                <form method="POST" action="{{ route('admin.payouts.reject', $payout) }}" class="row">
                                                    @csrf
                                                    <input class="input" name="reason" required placeholder="Reason (merchant sees it)" style="flex:1;min-width:0">
                                                    <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                                </form>
                                            </details>
                                        </div>
                                    @else
                                        <span class="dim">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-pager :paginator="$payouts" />
        @endif
    </div>
@endsection
