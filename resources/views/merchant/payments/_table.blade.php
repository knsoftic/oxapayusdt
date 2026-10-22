@if ($payments->isEmpty())
    <div class="empty">
        <x-icon name="inbox" size="34" />
        <strong>No payments yet</strong>
        Create one with a <a href="{{ route('links.index') }}">payment link</a> or the <a href="{{ route('docs') }}#create-payment">API</a>.
    </div>
@else
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Payment</th>
                    <th>Status</th>
                    <th class="num">Amount</th>
                    <th class="num">Fee</th>
                    <th class="num">Net</th>
                    <th>Customer</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr>
                        <td>
                            <a href="{{ route('payments.show', $payment) }}" class="row-link mono">{{ Str::limit($payment->public_id, 18) }}</a>
                            @if ($payment->reference)<div class="small dim">{{ $payment->reference }}</div>@endif
                        </td>
                        <td><x-status :status="$payment->publicStatus()" /></td>
                        <td class="num">{{ usd($payment->amount) }}</td>
                        <td class="num dim">{{ usd($payment->fee) }}</td>
                        <td class="num"><strong>{{ usd($payment->net_amount) }}</strong></td>
                        <td class="dim">{{ $payment->customer_email ?: '—' }}</td>
                        <td class="nowrap dim" title="{{ $payment->created_at }}">{{ $payment->created_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
