@if ($payments->isEmpty())
    <div class="empty"><x-icon name="inbox" size="34" /><strong>No payments</strong></div>
@else
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Payment</th><th>Merchant</th><th>Status</th><th class="num">Amount</th><th class="num">Fee</th><th>Paid with</th><th>Created</th></tr>
            </thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr>
                        <td>
                            <a href="{{ route('checkout.show', $payment) }}" target="_blank" rel="noopener" class="mono row-link">{{ Str::limit($payment->public_id, 18) }}</a>
                            @if ($payment->track_id)<div class="small dim">OxaPay #{{ $payment->track_id }}</div>@endif
                        </td>
                        <td><a href="{{ route('admin.merchants.show', $payment->merchant) }}">{{ $payment->merchant->business_name }}</a></td>
                        <td><x-status :status="$payment->publicStatus()" /></td>
                        <td class="num">{{ usd($payment->amount) }}</td>
                        <td class="num pos">{{ usd($payment->fee) }}</td>
                        <td class="dim">{{ $payment->pay_currency ? $payment->pay_amount.' '.$payment->pay_currency : '—' }}</td>
                        <td class="dim nowrap">{{ $payment->created_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
