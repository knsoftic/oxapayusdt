@extends('layouts.portal')

@section('title', 'Developers')
@section('subtitle', 'API keys and webhooks for your integration')

@section('actions')
    <a href="{{ route('docs') }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-icon name="book" size="16" /> Read the API docs</a>
@endsection

@section('content')
    @if (session('new_key'))
        <div class="alert alert-warn">
            <x-icon name="lock" />
            <div style="flex:1;min-width:0">
                <p><strong>Your new {{ session('new_key.mode') }} key.</strong> Copy it now. For your security it won't be shown again.</p>
                <div class="secret" style="margin-top:8px;background:var(--surface)">
                    <code>{{ session('new_key.key') }}</code>
                    <button type="button" class="copy" data-copy="{{ session('new_key.key') }}">Copy</button>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-head">
            <h2>API keys</h2>
            <span class="sub">Base URL <code>{{ url('/api/v1') }}</code></span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Mode</th><th>Secret key</th><th>Last used</th><th></th></tr></thead>
                <tbody>
                    @foreach (['test', 'live'] as $keyMode)
                        @php($key = $keys[$keyMode] ?? null)
                        <tr>
                            <td><span class="badge {{ $keyMode === 'test' ? 'warn' : 'ok' }}">{{ ucfirst($keyMode) }}</span></td>
                            <td>
                                @if ($key)
                                    <code>{{ $key->masked() }}</code>
                                    <div class="small dim">Created {{ $key->created_at->diffForHumans() }}</div>
                                @elseif ($keyMode === 'live' && ! $merchant->live_enabled)
                                    <span class="dim">Available after <a href="{{ route('settings.edit') }}#live">live activation</a></span>
                                @else
                                    <span class="dim">No key yet</span>
                                @endif
                            </td>
                            <td class="dim nowrap">{{ $key?->last_used_at?->diffForHumans() ?? '—' }}</td>
                            <td class="right">
                                @if ($keyMode === 'test' || $merchant->live_enabled)
                                    <form method="POST" action="{{ route('developers.keys.roll') }}" class="inline-form"
                                          @if ($key) onsubmit="return confirm('Create a new {{ $keyMode }} key? The current one stops working immediately.')" @endif>
                                        @csrf
                                        <input type="hidden" name="mode" value="{{ $keyMode }}">
                                        <button type="submit" class="btn {{ $key ? 'btn-secondary' : '' }} btn-sm">
                                            <x-icon :name="$key ? 'refresh' : 'plus'" size="14" /> {{ $key ? 'Roll key' : 'Create key' }}
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-foot small muted">
            Send the key as <code>Authorization: Bearer sk_...</code>. Keep it on your server: never put it in website or app code that customers can see.
        </div>
    </div>

    <div class="grid grid-2" id="webhook">
        <div class="card">
            <div class="card-head"><h2>Webhook endpoint</h2></div>
            <div class="card-body stack">
                <form method="POST" action="{{ route('developers.webhook') }}" class="form">
                    @csrf
                    @method('PUT')
                    <div class="field">
                        <label for="webhook_url">URL on your server</label>
                        <input class="input" id="webhook_url" name="webhook_url" type="url" value="{{ old('webhook_url', $merchant->webhook_url) }}" placeholder="https://yourshop.com/webhooks/payments">
                        <div class="hint">We POST payment and payout events here. Respond with any 2xx status.</div>
                    </div>
                    <div class="row">
                        <button type="submit" class="btn">Save</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('developers.webhook.test') }}" class="inline-form">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm" @disabled(! $merchant->webhook_url)><x-icon name="bolt" size="14" /> Send test event</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Signing secret</h2></div>
            <div class="card-body stack">
                <p class="small muted mt-0">Verify that webhooks really come from us: compute HMAC-SHA256 of <code>timestamp + "." + body</code> with this secret and compare it to the <code>X-Webhook-Signature</code> header. <a href="{{ route('docs') }}#webhooks">See code</a></p>
                <details class="inline">
                    <summary>Reveal secret</summary>
                    <div class="secret">
                        <code>{{ $merchant->webhook_secret }}</code>
                        <button type="button" class="copy" data-copy="{{ $merchant->webhook_secret }}">Copy</button>
                    </div>
                </details>
                <form method="POST" action="{{ route('developers.webhook.secret') }}" class="inline-form" onsubmit="return confirm('Create a new signing secret? Webhooks will be signed with the new one from now on.')">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm"><x-icon name="refresh" size="14" /> Roll secret</button>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Recent webhook deliveries</h2><span class="sub">{{ ucfirst($mode) }} mode</span></div>
        @if ($deliveries->isEmpty())
            <div class="empty">No webhooks sent yet.</div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Event</th><th>Status</th><th>Response</th><th class="num">Attempts</th><th>Sent</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($deliveries as $delivery)
                            <tr>
                                <td><code>{{ $delivery->event }}</code><div class="small dim mono">{{ $delivery->event_id }}</div></td>
                                <td><x-status :status="$delivery->status" /></td>
                                <td class="dim">{{ $delivery->response_code ? 'HTTP '.$delivery->response_code : ($delivery->last_attempt_at ? 'No response' : '—') }}</td>
                                <td class="num">{{ $delivery->attempts }}</td>
                                <td class="dim nowrap">{{ ($delivery->last_attempt_at ?? $delivery->created_at)->diffForHumans() }}</td>
                                <td>
                                    <details class="inline">
                                        <summary>Details</summary>
                                        <div class="small"><strong>URL</strong> <code>{{ $delivery->url }}</code></div>
                                        <pre class="code" style="max-width:520px;white-space:pre-wrap">{{ json_encode(json_decode($delivery->payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        @if ($delivery->response_body)
                                            <div class="small"><strong>Response body</strong></div>
                                            <pre class="code" style="max-width:520px;white-space:pre-wrap">{{ $delivery->response_body }}</pre>
                                        @endif
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
