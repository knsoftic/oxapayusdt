@extends('layouts.portal')

@section('title', 'Payment links')
@section('subtitle', 'Share a link by chat, email or social media. No code needed.')

@section('content')
    <div class="grid grid-main">
        <div class="card">
            <div class="card-head"><h2>Your links</h2><span class="sub">{{ ucfirst($mode) }} mode</span></div>
            @if ($links->isEmpty())
                <div class="empty">
                    <x-icon name="link" size="34" />
                    <strong>No payment links yet</strong>
                    Create your first one with the form.
                </div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Link</th><th class="num">Amount</th><th class="num">Paid</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($links as $link)
                                <tr>
                                    <td>
                                        <strong>{{ $link->title }}</strong>
                                        <div class="row small" style="margin-top:4px">
                                            <code class="dim">{{ Str::after($link->url(), '://') }}</code>
                                            <button type="button" class="copy" data-copy="{{ $link->url() }}">Copy</button>
                                        </div>
                                    </td>
                                    <td class="num">{{ $link->amount ? usd($link->amount) : 'Customer chooses' }}</td>
                                    <td class="num">{{ $link->paid_count }}</td>
                                    <td><span class="badge {{ $link->active ? 'ok' : '' }}">{{ $link->active ? 'On' : 'Off' }}</span></td>
                                    <td class="right nowrap">
                                        <a href="{{ $link->url() }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">Open</a>
                                        <form method="POST" action="{{ route('links.toggle', $link) }}" class="inline-form">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-secondary btn-sm">{{ $link->active ? 'Turn off' : 'Turn on' }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-head"><h2>New link</h2></div>
            <div class="card-body">
                <form method="POST" action="{{ route('links.store') }}" class="form">
                    @csrf
                    <div class="field">
                        <label for="title">Title</label>
                        <input class="input" id="title" name="title" value="{{ old('title') }}" required maxlength="120" placeholder="e.g. Logo design deposit">
                    </div>
                    <div class="field">
                        <label for="amount">Amount (USD)</label>
                        <input class="input" id="amount" name="amount" type="number" step="0.01" min="{{ config('platform.min_payment') }}" value="{{ old('amount') }}" placeholder="Leave empty to let the customer choose">
                    </div>
                    <div class="field">
                        <label for="description">Description <span class="muted">(optional)</span></label>
                        <input class="input" id="description" name="description" value="{{ old('description') }}" maxlength="255">
                    </div>
                    <p class="small muted mt-0">Fee per payment: {{ $merchant->payinFeeLabel() }}. The rest goes to your balance.</p>
                    <button type="submit" class="btn"><x-icon name="plus" size="15" /> Create link</button>
                </form>
            </div>
        </div>
    </div>
@endsection
