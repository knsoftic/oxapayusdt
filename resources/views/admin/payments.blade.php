@extends('layouts.portal')

@section('title', 'Payments')
@section('subtitle', 'All merchants · '.$mode.' mode')

@section('content')
    <div class="card">
        <div class="card-head">
            <form method="GET" class="row" style="width:100%">
                <select name="mode" class="select" style="max-width:120px" onchange="this.form.submit()" aria-label="Mode">
                    <option value="live" @selected($mode === 'live')>Live</option>
                    <option value="test" @selected($mode === 'test')>Test</option>
                </select>
                <select name="status" class="select" style="max-width:170px" onchange="this.form.submit()" aria-label="Status">
                    <option value="">All statuses</option>
                    @foreach (array_keys(\App\Models\Payment::PUBLIC_STATUSES) as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                    @endforeach
                </select>
                @if (request('merchant'))<input type="hidden" name="merchant" value="{{ request('merchant') }}">@endif
                <input type="search" name="q" value="{{ $search }}" class="input" style="max-width:280px" placeholder="Payment ID, OxaPay track ID, reference">
                <button type="submit" class="btn btn-secondary">Search</button>
            </form>
        </div>
        @include('admin._payments_table', ['payments' => $payments])
        <x-pager :paginator="$payments" />
    </div>
@endsection
