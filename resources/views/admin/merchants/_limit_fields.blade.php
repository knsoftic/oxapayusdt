{{-- Payment and withdrawal amount limits; empty = platform default. Expects $merchant (may be null). --}}
@php
    $defaultLabel = fn ($value) => is_numeric($value) ? rtrim(rtrim(number_format((float) $value, 2), '0'), '.').' (default)' : 'No limit (default)';
    $fields = [
        ['limit_payin_min', 'Min payment $', config('platform.min_payment'), '0.5'],
        ['limit_payin_max', 'Max payment $', config('platform.max_payment'), '0.5'],
        ['limit_payout_min', 'Min withdrawal $', config('platform.min_payout'), '1'],
        ['limit_payout_max', 'Max withdrawal $', config('platform.max_payout'), '1'],
    ];
@endphp
<div class="form-row">
    @foreach ($fields as [$name, $label, $default, $min])
        <div class="field">
            <label for="{{ $name }}">{{ $label }}</label>
            <input class="input" id="{{ $name }}" name="{{ $name }}" type="number" step="0.01" min="{{ $min }}" max="1000000"
                   value="{{ old($name, $merchant?->{$name}) }}" placeholder="{{ $defaultLabel($default) }}">
        </div>
    @endforeach
</div>
<p class="small muted mt-0">
    Leave a field empty to use the platform default.
    @if ($merchant)
        Now: payments <strong>{{ $merchant->payinLimitLabel() }}</strong>, withdrawals <strong>{{ $merchant->payoutLimitLabel() }}</strong>.
    @endif
</p>
