@extends('layouts.portal')

@section('title', usd($payment->amount).' payment')
@section('subtitle', $payment->public_id)

@section('actions')
    <a href="{{ route('checkout.show', $payment) }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-icon name="external" size="15" /> Open checkout</a>
@endsection

@section('content')
    <div class="grid grid-main">
        <div class="stack">
            <div class="card">
                <div class="card-head">
                    <h2>Details</h2>
                    <div class="right"><x-status :status="$payment->publicStatus()" /> <span class="badge plain">{{ ucfirst($payment->mode) }}</span></div>
                </div>
                <div class="card-body">
                    <dl class="dl">
                        <dt>Amount</dt><dd>{{ usd($payment->amount) }} {{ $payment->currency }}</dd>
                        <dt>Fee</dt><dd>{{ usd($payment->fee) }}</dd>
                        <dt>Net to balance</dt><dd><strong>{{ usd($payment->net_amount) }}</strong></dd>
                        @if ($payment->pay_currency)
                            <dt>Paid with</dt><dd>{{ $payment->pay_amount }} {{ $payment->pay_currency }} · {{ $payment->network }}</dd>
                        @endif
                        <dt>Reference</dt><dd>{{ $payment->reference ?: '—' }}</dd>
                        <dt>Description</dt><dd>{{ $payment->description ?: '—' }}</dd>
                        <dt>Customer</dt><dd>{{ $payment->customer_email ?: '—' }}</dd>
                        <dt>Created</dt><dd>{{ $payment->created_at->format('d M Y, H:i') }}</dd>
                        <dt>Expires</dt><dd>{{ $payment->expires_at?->format('d M Y, H:i') ?? '—' }}</dd>
                        <dt>Paid</dt><dd>{{ $payment->paid_at?->format('d M Y, H:i') ?? '—' }}</dd>
                        <dt>Checkout link</dt>
                        <dd><div class="secret"><code>{{ route('checkout.show', $payment) }}</code><button type="button" class="copy" data-copy="{{ route('checkout.show', $payment) }}">Copy</button></div></dd>
                    </dl>
                </div>
            </div>

            @if ($payment->metadata)
                <div class="card">
                    <div class="card-head"><h2>Metadata</h2></div>
                    <div class="card-body">
                        <dl class="dl">
                            @foreach ($payment->metadata as $key => $value)
                                <dt class="mono">{{ $key }}</dt><dd>{{ $value }}</dd>
                            @endforeach
                        </dl>
                    </div>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-head"><h2>Balance entries</h2></div>
            @if ($entries->isEmpty())
                <div class="empty">Nothing is added to your balance until the payment is paid.</div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <tbody>
                            @foreach ($entries as $entry)
                                <tr>
                                    <td>{{ $entry->label() }}</td>
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
