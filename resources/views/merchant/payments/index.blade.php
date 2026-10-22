@extends('layouts.portal')

@section('title', 'Payments')
@section('subtitle', 'Every payment created for '.$merchant->business_name.' in '.$mode.' mode')

@section('content')
    <div class="card">
        <div class="card-head">
            <form method="GET" class="row" style="width:100%">
                <select name="status" class="select" style="max-width:180px" onchange="this.form.submit()" aria-label="Status">
                    <option value="">All statuses</option>
                    @foreach (array_keys(\App\Models\Payment::PUBLIC_STATUSES) as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                    @endforeach
                </select>
                <input type="search" name="q" value="{{ $search }}" class="input" style="max-width:300px" placeholder="Payment ID, reference or email">
                <button type="submit" class="btn btn-secondary">Search</button>
            </form>
        </div>

        @include('merchant.payments._table', ['payments' => $payments])
        <x-pager :paginator="$payments" />
    </div>
@endsection
