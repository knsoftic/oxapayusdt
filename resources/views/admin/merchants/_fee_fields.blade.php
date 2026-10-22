{{-- Pay-in and payout fee overrides; empty = platform default. Expects $merchant (may be null). --}}
@php
    $fields = [
        ['fee_payin_percent', 'Pay-in %', config('platform.fees.payin_percent'), '50'],
        ['fee_payin_fixed', 'Pay-in fixed $', config('platform.fees.payin_fixed'), '1000'],
        ['fee_payout_percent', 'Payout %', config('platform.fees.payout_percent'), '50'],
        ['fee_payout_fixed', 'Payout fixed $', config('platform.fees.payout_fixed'), '1000'],
    ];
@endphp
<div class="form-row">
    @foreach ($fields as [$name, $label, $default, $max])
        <div class="field">
            <label for="{{ $name }}">{{ $label }}</label>
            <input class="input" id="{{ $name }}" name="{{ $name }}" type="number" step="0.01" min="0" max="{{ $max }}"
                   value="{{ old($name, $merchant?->{$name}) }}" placeholder="{{ rtrim(rtrim(number_format((float) $default, 2), '0'), '.') ?: '0' }} (default)">
        </div>
    @endforeach
</div>
<p class="small muted mt-0">
    Fee = amount × % + fixed. Leave a field empty to use the platform default
    ({{ \App\Models\Merchant::feeLabel(config('platform.fees.payin_percent'), config('platform.fees.payin_fixed')) }} per payment,
    {{ \App\Models\Merchant::feeLabel(config('platform.fees.payout_percent'), config('platform.fees.payout_fixed')) }} per withdrawal).
    @if ($merchant)
        Now: <strong>{{ $merchant->payinFeeLabel() }}</strong> per payment, <strong>{{ $merchant->payoutFeeLabel() }}</strong> per withdrawal.
    @endif
</p>
