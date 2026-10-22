@extends('layouts.portal')

@section('title', 'Merchants')
@section('subtitle', 'Everyone using your gateway')

@section('actions')
    <a href="{{ route('admin.merchants.create') }}" class="btn"><x-icon name="plus" size="16" /> Add merchant</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <form method="GET" class="row" style="width:100%">
                <input type="search" name="q" value="{{ $search }}" class="input" style="max-width:300px" placeholder="Business or email">
                <button type="submit" class="btn btn-secondary">Search</button>
                <span class="spacer"></span>
                <a href="{{ route('admin.merchants.index') }}" class="btn btn-ghost btn-sm {{ request('filter') ? '' : 'active' }}">All</a>
                <a href="{{ route('admin.merchants.index', ['filter' => 'requests']) }}" class="btn btn-ghost btn-sm">Live requests</a>
            </form>
        </div>

        @if ($merchants->isEmpty())
            <div class="empty"><x-icon name="users" size="34" /><strong>No merchants found</strong><a href="{{ route('admin.merchants.create') }}">Add your first merchant</a></div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Merchant</th><th>Status</th><th>Live</th><th class="num">Live balance</th><th class="num">Paid</th><th>Joined</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($merchants as $m)
                            <tr>
                                <td><a href="{{ route('admin.merchants.show', $m) }}" class="row-link">{{ $m->business_name }}</a><div class="small dim">{{ $m->user->email }}</div></td>
                                <td><span class="badge {{ $m->isActive() ? 'ok' : 'bad' }}">{{ ucfirst($m->status) }}</span></td>
                                <td>
                                    @if ($m->live_enabled)
                                        <span class="badge ok">Live</span>
                                    @elseif ($m->live_requested_at)
                                        <span class="badge warn">Requested</span>
                                    @else
                                        <span class="badge">Test only</span>
                                    @endif
                                </td>
                                <td class="num">{{ usd($m->live_balance) }}</td>
                                <td class="num">{{ $m->paid_count }}</td>
                                <td class="dim nowrap">{{ $m->created_at->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-pager :paginator="$merchants" />
        @endif
    </div>
@endsection
