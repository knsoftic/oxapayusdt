@props(['status'])
@php
    // Works for payment public statuses and payout statuses.
    $class = match ($status) {
        'paid', 'completed', 'succeeded' => 'ok',
        'pending', 'confirming', 'processing', 'retrying' => 'warn',
        'underpaid', 'failed', 'rejected' => 'bad',
        'refunded' => 'info',
        default => '',
    };
@endphp
<span {{ $attributes->merge(['class' => "badge {$class}"]) }}>{{ ucfirst($status) }}</span>
