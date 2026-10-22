@extends('layouts.checkout')

@section('title', $link->title)
@section('narrow', '1')

@section('content')
    <form method="POST" action="{{ route('links.pay', $link) }}" class="co-card">
        @csrf

        @if ($errors->any())
            <div class="alert" role="alert">
                <div>
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        <h1 class="co-title" style="font-size:20px">{{ $link->title }}</h1>
        @if ($link->description)
            <p class="muted" style="margin:-8px 0 18px">{{ $link->description }}</p>
        @endif

        @if ($link->amount)
            <div class="summary-total" style="margin:0 0 18px;padding:0;border:0">
                <span>Amount</span>
                <strong>{{ number_format((float) $link->amount, 2) }} USD</strong>
            </div>
        @else
            <div class="field">
                <label for="amount">Amount (USD)</label>
                <div class="amount-row">
                    <input class="input" id="amount" type="number" name="amount" step="0.01" min="{{ config('platform.min_payment') }}" value="{{ old('amount') }}" required autofocus>
                    <input class="input" type="text" value="USD" disabled aria-label="Currency">
                </div>
            </div>
        @endif

        <div class="field">
            <label for="customer_email">Email for receipt <span>(optional)</span></label>
            <input class="input" id="customer_email" type="email" name="customer_email" value="{{ old('customer_email') }}" placeholder="you@example.com">
        </div>

        <button type="submit" class="btn">
            Continue
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
    </form>
@endsection
