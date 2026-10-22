@php
    $platform = config('platform.name');
    $networks = config('platform.payout_networks');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API documentation · {{ $platform }}</title>
    <meta name="description" content="Accept crypto payments with the {{ $platform }} API: create payments, receive webhooks, check your balance and withdraw.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}">
    <style>:root { {{ brand_vars(config('platform.brand_color')) }} }</style>
</head>
<body>
    <header class="docs-header">
        <a href="{{ url('/') }}" class="brand" style="padding:0">
            <span class="brand-mark">{{ Str::upper(Str::substr($platform, 0, 1)) }}</span>
            <span>{{ $platform }} <small>API documentation · v1</small></span>
        </a>
        <div class="right">
            <a href="{{ asset('downloads/postman_collection.json') }}" class="btn btn-secondary btn-sm" download>Postman collection</a>
            @auth
                <a href="{{ auth()->user()->homeRoute() }}" class="btn btn-sm">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-sm">Log in</a>
            @endauth
        </div>
    </header>

    <div class="docs">
        <nav class="docs-nav">
            <div class="nav-label">Getting started</div>
            <a href="#introduction">Introduction</a>
            <a href="#quick-start">Quick start</a>
            <a href="#authentication">Authentication</a>
            <a href="#test-mode">Test mode</a>
            <a href="#fees">Fees, limits, balance</a>
            <div class="nav-label">Payments</div>
            <a href="#create-payment">Create a payment</a>
            <a href="#retrieve-payment">Retrieve a payment</a>
            <a href="#list-payments">List payments</a>
            <a href="#statuses">Payment statuses</a>
            <div class="nav-label">Money out</div>
            <a href="#balance">Balance</a>
            <a href="#payouts">Payouts</a>
            <div class="nav-label">Webhooks</div>
            <a href="#webhooks">Events and payload</a>
            <a href="#verify">Verify signatures</a>
            <div class="nav-label">Reference</div>
            <a href="#errors">Errors</a>
            <a href="#idempotency">Idempotency</a>
            <a href="#examples">Full examples</a>
        </nav>

        <div class="docs-body">
            <section id="introduction">
                <h1>{{ $platform }} API</h1>
                <p class="lead">Accept Bitcoin, USDT, Ethereum and other cryptocurrencies on your website. You create a payment, send your customer to the checkout page, and we tell your server the moment it's paid. The money (minus fees) lands in your balance, which you can withdraw as USDT.</p>
                <p>The API is REST over HTTPS. It accepts JSON bodies and always answers with JSON. All amounts are strings with two decimals, in USD.</p>
                <p><span class="endpoint">Base URL <code>{{ $baseUrl }}</code></span></p>
            </section>

            <section id="quick-start">
                <h2>Quick start</h2>
                <ol>
                    <li>Get your merchant account from the {{ $platform }} team, <a href="{{ route('login') }}">log in</a>, open <strong>Developers</strong> and create a <strong>test key</strong> (<code>sk_test_...</code>).</li>
                    <li>Create a payment from your server:</li>
                </ol>
                @verbatim
                <pre class="code">curl -X POST __BASE_URL__/payments \
  -H "Authorization: Bearer sk_test_YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": "25.00",
    "reference": "order_1001",
    "description": "2x T-shirt",
    "customer_email": "buyer@example.com",
    "return_url": "https://yourshop.com/thank-you"
  }'</pre>
                @endverbatim
                <ol start="3">
                    <li>Redirect the customer to the <code>checkout_url</code> in the response.</li>
                    <li>When the customer pays, we send <code>payment.paid</code> to your <a href="#webhooks">webhook URL</a>. Mark the order as paid there. Don't rely on the customer coming back to your site.</li>
                </ol>
            </section>

            <section id="authentication">
                <h2>Authentication</h2>
                <p>Every request needs your secret key in the <code>Authorization</code> header:</p>
                <pre class="code">Authorization: Bearer sk_live_YOUR_SECRET_KEY</pre>
                <p>The key decides both the account and the mode. <code>sk_test_</code> keys only see test data; <code>sk_live_</code> keys only see live data. Keys are shown once when created. If one leaks, roll it in <strong>Developers</strong>; the old key stops working immediately.</p>
                <div class="alert alert-warn"><x-icon name="lock" /><p>Use the key only on your server. Never put it in a browser, a mobile app or a public repository.</p></div>
            </section>

            <section id="test-mode">
                <h2>Test mode</h2>
                <p>With a test key, everything works end to end without real money:</p>
                <ul>
                    <li>Open the payment's <code>checkout_url</code>, press <strong>Pay with crypto</strong>, choose a coin on the test page and press <strong>Simulate Payment</strong>. The payment becomes <code>paid</code>, your test balance goes up and your webhook receives <code>payment.paid</code>.</li>
                    <li>Test payouts complete instantly with a fake transaction hash, so you can build your withdrawal flow too.</li>
                </ul>
                <p>Live mode is enabled after a short review. Request it from <strong>Settings → Live payments</strong>.</p>
            </section>

            <section id="fees">
                <h2>Fees, limits and balance</h2>
                <p>Each payment has a pay-in fee (default: {{ rtrim(rtrim(number_format(config('platform.fees.payin_percent'), 2), '0'), '.') }}% + {{ usd(config('platform.fees.payin_fixed')) }}). It is fixed when the payment is created and returned as <code>fee</code> and <code>net_amount</code>. When the payment is paid, the <code>net_amount</code> is added to your balance.</p>
                <p>Each withdrawal has a payout fee (default {{ \App\Models\Merchant::feeLabel(config('platform.fees.payout_percent'), config('platform.fees.payout_fixed')) }}), taken from the withdrawal amount. Both fees are a percentage plus a fixed amount; <code>GET /balance</code> returns the exact fees for your account.</p>
                <table class="params">
                    <tr><td>Customer pays</td><td>$10.00</td></tr>
                    <tr><td>Pay-in fee</td><td>− {{ usd(config('platform.fees.payin_fixed') + 10 * config('platform.fees.payin_percent') / 100) }}</td></tr>
                    <tr><td><strong>Added to your balance</strong></td><td><strong>{{ usd(10 - config('platform.fees.payin_fixed') - 10 * config('platform.fees.payin_percent') / 100) }}</strong></td></tr>
                </table>
                <p><strong>Amount limits.</strong> Every account has a minimum and maximum amount per payment and per withdrawal. A payment or withdrawal outside them is refused with <code>422 invalid_request</code> and a message on the <code>amount</code> field, such as <code>"The maximum payment is $5,000.00."</code></p>
                <table class="params">
                    <tr><td>Payment amount (default)</td><td>{{ \App\Models\Merchant::limitLabel((float) config('platform.min_payment'), is_numeric(config('platform.max_payment')) ? (float) config('platform.max_payment') : null) }}</td></tr>
                    <tr><td>Withdrawal amount (default)</td><td>{{ \App\Models\Merchant::limitLabel((float) config('platform.min_payout'), is_numeric(config('platform.max_payout')) ? (float) config('platform.max_payout') : null) }}</td></tr>
                </table>
                <p class="small muted">Your own fees and limits may differ. <code>GET /balance</code> returns them under <code>fees</code> and <code>limits</code>, and the dashboard shows them under Settings.</p>
            </section>

            <section id="create-payment">
                <h2>Create a payment</h2>
                <p><span class="endpoint"><span class="method post">POST</span> /payments</span></p>
                <table class="params">
                    <tr><td><code>amount</code> <span class="req">required</span><span class="type">string or number</span></td><td>Amount in USD, e.g. <code>"25.00"</code>. Must be within your <a href="#fees">payment limits</a> (default {{ \App\Models\Merchant::limitLabel((float) config('platform.min_payment'), is_numeric(config('platform.max_payment')) ? (float) config('platform.max_payment') : null) }}).</td></tr>
                    <tr><td><code>currency</code><span class="type">string</span></td><td>Only <code>USD</code> for now. The customer still pays in crypto.</td></tr>
                    <tr><td><code>reference</code><span class="type">string, max 100</span></td><td>Your own order ID. Returned in webhooks and searchable.</td></tr>
                    <tr><td><code>description</code><span class="type">string, max 255</span></td><td>Shown to the customer on the checkout.</td></tr>
                    <tr><td><code>customer_email</code><span class="type">email</span></td><td>The customer's email, for receipts.</td></tr>
                    <tr><td><code>callback_url</code><span class="type">URL</span></td><td>Send this payment's webhooks here instead of your account's webhook URL.</td></tr>
                    <tr><td><code>return_url</code><span class="type">URL</span></td><td>Where the "Return to your store" button sends the customer after paying.</td></tr>
                    <tr><td><code>metadata</code><span class="type">object, up to 20 keys</span></td><td>Your own string key/values, returned as-is.</td></tr>
                </table>
                <p>The payment stays open for {{ config('oxapay.lifetime') }} minutes. Response <code>201 Created</code>:</p>
                @verbatim
                <pre class="code">{
  "id": "pay_01k8a3k9xw2v7d5j1m4q0r6t8z",
  "object": "payment",
  "mode": "test",
  "status": "pending",
  "amount": "25.00",
  "currency": "USD",
  "fee": "0.50",
  "net_amount": "24.50",
  "reference": "order_1001",
  "description": "2x T-shirt",
  "customer_email": "buyer@example.com",
  "checkout_url": "__CHECKOUT_URL__",
  "pay_currency": null,
  "pay_amount": null,
  "network": null,
  "metadata": {},
  "expires_at": "2026-10-22T12:00:00+00:00",
  "paid_at": null,
  "created_at": "2026-10-22T11:00:00+00:00"
}</pre>
                @endverbatim
                <p><code>pay_currency</code>, <code>pay_amount</code> and <code>network</code> are filled once the customer picks a coin.</p>
            </section>

            <section id="retrieve-payment">
                <h2>Retrieve a payment</h2>
                <p><span class="endpoint"><span class="method get">GET</span> /payments/{id}</span></p>
                <p>Returns the payment object with its latest status. Use it to double-check a webhook, or to poll if you can't receive webhooks.</p>
            </section>

            <section id="list-payments">
                <h2>List payments</h2>
                <p><span class="endpoint"><span class="method get">GET</span> /payments</span></p>
                <table class="params">
                    <tr><td><code>status</code><span class="type">string</span></td><td>Only payments with this <a href="#statuses">status</a>.</td></tr>
                    <tr><td><code>reference</code><span class="type">string</span></td><td>Only payments with this exact reference.</td></tr>
                    <tr><td><code>limit</code><span class="type">1–100, default 20</span></td><td>Page size.</td></tr>
                    <tr><td><code>page</code><span class="type">integer</span></td><td>Page number.</td></tr>
                </table>
                <p>Returns <code>{"data": [ ...payments ], "links": {...}, "meta": {"current_page", "last_page", "total", ...}}</code>, newest first.</p>
            </section>

            <section id="statuses">
                <h2>Payment statuses</h2>
                <table class="params">
                    @foreach ($statuses as $name => $meaning)
                        <tr><td><code>{{ $name }}</code></td><td>{{ $meaning }}</td></tr>
                    @endforeach
                </table>
                <p>Only <code>paid</code> means you have the money. Fulfil orders on <code>paid</code>, never on <code>confirming</code>.</p>
            </section>

            <section id="balance">
                <h2>Balance</h2>
                <p><span class="endpoint"><span class="method get">GET</span> /balance</span></p>
                @verbatim
                <pre class="code">{
  "object": "balance",
  "mode": "live",
  "currency": "USD",
  "available": "1240.50",
  "pending_payouts": "100.00",
  "fees": {
    "payin_percent": "1.00",
    "payin_fixed": "0.50",
    "payout_percent": "0.00",
    "payout_fixed": "1.00"
  },
  "limits": {
    "payment_min": "1.00",
    "payment_max": "5000.00",
    "payout_min": "5.00",
    "payout_max": null
  }
}</pre>
                @endverbatim
                <p><code>limits</code> are in USD; a <code>null</code> maximum means there is no upper limit.</p>
                @verbatim
                @endverbatim
            </section>

            <section id="payouts">
                <h2>Payouts</h2>
                <p>Withdraw your balance to your own wallet in <strong>{{ config('platform.payout_currency') }}</strong>. The amount (including the payout fee) leaves your balance right away. Live payouts are reviewed and then sent; if one is rejected or fails, the full amount returns to your balance.</p>
                <p><span class="endpoint"><span class="method post">POST</span> /payouts</span></p>
                <table class="params">
                    <tr><td><code>amount</code> <span class="req">required</span><span class="type">number, USD</span></td><td>Taken from your balance. You receive <code>amount − fee</code> {{ config('platform.payout_currency') }}. Must be within your <a href="#fees">withdrawal limits</a> (default {{ \App\Models\Merchant::limitLabel((float) config('platform.min_payout'), is_numeric(config('platform.max_payout')) ? (float) config('platform.max_payout') : null) }}).</td></tr>
                    <tr><td><code>network</code> <span class="req">required</span><span class="type">string</span></td><td>One of: @foreach ($networks as $value => $label)<code>{{ $value }}</code> ({{ $label }})@if (! $loop->last), @endif @endforeach</td></tr>
                    <tr><td><code>address</code> <span class="req">required</span><span class="type">string</span></td><td>Your wallet address on that network. It is format-checked.</td></tr>
                    <tr><td><code>memo</code><span class="type">string</span></td><td>Memo / tag, only if your wallet needs one.</td></tr>
                </table>
                @verbatim
                <pre class="code">{
  "id": "po_01k8a4b2c3d4e5f6g7h8j9k0m1",
  "object": "payout",
  "mode": "live",
  "status": "pending",
  "amount": "50.00",
  "fee": "1.00",
  "net_amount": "49.00",
  "currency": "USDT",
  "network": "Tron",
  "address": "TQ7m2w...",
  "memo": null,
  "tx_hash": null,
  "failure_reason": null,
  "created_at": "2026-10-22T11:00:00+00:00",
  "completed_at": null
}</pre>
                @endverbatim
                <p>Statuses: <code>pending</code> (waiting for review), <code>processing</code> (sent, waiting for the blockchain), <code>completed</code>, <code>rejected</code> or <code>failed</code> (amount returned, see <code>failure_reason</code>).</p>
                <p><span class="endpoint"><span class="method get">GET</span> /payouts</span> &nbsp; <span class="endpoint"><span class="method get">GET</span> /payouts/{id}</span></p>
            </section>

            <section id="webhooks">
                <h2>Webhooks</h2>
                <p>Set your endpoint in <strong>Developers → Webhook endpoint</strong> (or per payment with <code>callback_url</code>). We send a <code>POST</code> with a JSON body:</p>
                @verbatim
                <pre class="code">{
  "id": "evt_01k8a3m0p2q4r6s8t0v2w4x6y8",
  "type": "payment.paid",
  "mode": "live",
  "created_at": "2026-10-22T11:04:10+00:00",
  "data": { ...the payment object... }
}</pre>
                @endverbatim
                <table class="params">
                    <tr><td><code>payment.confirming</code></td><td>The customer sent crypto; it's waiting for confirmations.</td></tr>
                    <tr><td><code>payment.paid</code></td><td>Paid. The net amount is in your balance. <strong>Fulfil the order here.</strong></td></tr>
                    <tr><td><code>payment.underpaid</code></td><td>Less than required arrived.</td></tr>
                    <tr><td><code>payment.expired</code></td><td>Not paid in time.</td></tr>
                    <tr><td><code>payment.refunded</code></td><td>The payment was refunded.</td></tr>
                    <tr><td><code>payout.completed</code></td><td>A withdrawal was sent. <code>data</code> is the payout object.</td></tr>
                    <tr><td><code>payout.failed</code></td><td>A withdrawal was rejected or failed; the amount is back in your balance.</td></tr>
                    <tr><td><code>test.ping</code></td><td>Sent by the "Send test event" button.</td></tr>
                </table>
                <p>Answer with any <code>2xx</code> status within {{ config('platform.webhooks.timeout') }} seconds. Otherwise we retry after 1 minute, 5 minutes, 30 minutes, 1 hour and 3 hours. Every attempt is listed in <strong>Developers</strong>. The same event may arrive more than once, so use the event <code>id</code> (or the payment <code>id</code> + <code>type</code>) to skip duplicates.</p>
            </section>

            <section id="verify">
                <h2>Verify signatures</h2>
                <p>Each webhook has three headers:</p>
                <table class="params">
                    <tr><td><code>X-Webhook-Id</code></td><td>The event ID.</td></tr>
                    <tr><td><code>X-Webhook-Timestamp</code></td><td>Unix time when it was signed.</td></tr>
                    <tr><td><code>X-Webhook-Signature</code></td><td>Hex HMAC-SHA256 of <code>timestamp + "." + raw_body</code>, keyed with your signing secret (<code>whsec_...</code>, in <strong>Developers</strong>).</td></tr>
                </table>
                <p>Always use the <strong>raw</strong> request body (not re-encoded JSON), compare in constant time, and reject timestamps older than 5 minutes.</p>
                <div class="code-tabs" data-tabs>
                    <div class="tab-bar"><button type="button" class="on">PHP</button><button type="button">Node.js</button><button type="button">Python</button></div>
                    @verbatim
                    <pre class="code">&lt;?php
$secret    = getenv('WEBHOOK_SECRET'); // whsec_...
$payload   = file_get_contents('php://input');
$timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';

$expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

if (! hash_equals($expected, $signature) || abs(time() - (int) $timestamp) > 300) {
    http_response_code(400);
    exit('Invalid signature');
}

$event = json_decode($payload, true);

if ($event['type'] === 'payment.paid') {
    $payment = $event['data'];
    // Mark order $payment['reference'] as paid (once).
}

http_response_code(200);
echo 'ok';</pre>
                    <pre class="code" hidden>// Express: keep the raw body for this route
const crypto = require('crypto');
const express = require('express');
const app = express();

app.post('/webhooks/payments', express.raw({ type: 'application/json' }), (req, res) =&gt; {
  const timestamp = req.get('X-Webhook-Timestamp');
  const signature = req.get('X-Webhook-Signature') || '';
  const expected = crypto
    .createHmac('sha256', process.env.WEBHOOK_SECRET)
    .update(`${timestamp}.${req.body}`)
    .digest('hex');

  const valid = signature.length === expected.length &amp;&amp;
    crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected));

  if (!valid || Math.abs(Date.now() / 1000 - Number(timestamp)) &gt; 300) {
    return res.status(400).send('Invalid signature');
  }

  const event = JSON.parse(req.body);
  if (event.type === 'payment.paid') {
    // Mark order event.data.reference as paid (once).
  }
  res.send('ok');
});</pre>
                    <pre class="code" hidden># Flask
import hmac, hashlib, os, time
from flask import Flask, request, abort

app = Flask(__name__)

@app.post('/webhooks/payments')
def webhook():
    payload = request.get_data()
    timestamp = request.headers.get('X-Webhook-Timestamp', '')
    signature = request.headers.get('X-Webhook-Signature', '')
    expected = hmac.new(os.environ['WEBHOOK_SECRET'].encode(),
                        f'{timestamp}.'.encode() + payload, hashlib.sha256).hexdigest()

    if not hmac.compare_digest(expected, signature) or abs(time.time() - int(timestamp or 0)) &gt; 300:
        abort(400)

    event = request.get_json()
    if event['type'] == 'payment.paid':
        pass  # Mark order event['data']['reference'] as paid (once).
    return 'ok'</pre>
                    @endverbatim
                </div>
            </section>

            <section id="errors">
                <h2>Errors</h2>
                <p>Errors use standard HTTP status codes and one JSON shape:</p>
                @verbatim
                <pre class="code">{
  "error": {
    "type": "invalid_request",
    "message": "The amount field is required.",
    "fields": { "amount": ["The amount field is required."] }
  }
}</pre>
                @endverbatim
                <table class="params">
                    <tr><td><code>401</code> authentication_error</td><td>Missing, invalid or revoked API key.</td></tr>
                    <tr><td><code>403</code> account_suspended</td><td>The merchant account is suspended.</td></tr>
                    <tr><td><code>403</code> live_mode_disabled</td><td>A live key was used before live activation.</td></tr>
                    <tr><td><code>404</code> not_found</td><td>No such payment or payout in this mode.</td></tr>
                    <tr><td><code>422</code> invalid_request</td><td>A field is missing or wrong; see <code>fields</code>. Also returned for an amount outside your limits, and for insufficient balance on payouts.</td></tr>
                    <tr><td><code>429</code> rate_limited</td><td>More than 120 requests per minute with one key.</td></tr>
                    <tr><td><code>502</code> gateway_error</td><td>The payment network had a problem. Safe to retry.</td></tr>
                </table>
            </section>

            <section id="idempotency">
                <h2>Idempotency</h2>
                <p>Network errors happen. To retry <code>POST /payments</code> or <code>POST /payouts</code> safely, send an <code>Idempotency-Key</code> header (any unique string up to 100 characters, e.g. your order ID). A repeated request with the same key returns the original object with status <code>200</code> instead of creating a second one.</p>
                <pre class="code">Idempotency-Key: order_1001</pre>
            </section>

            <section id="examples" style="border-bottom:0">
                <h2>Full examples</h2>
                <h3>Create a payment and redirect</h3>
                <div class="code-tabs" data-tabs>
                    <div class="tab-bar"><button type="button" class="on">PHP</button><button type="button">Laravel</button><button type="button">Node.js</button></div>
                    @verbatim
                    <pre class="code">&lt;?php
$ch = curl_init('__BASE_URL__/payments');
curl_setopt_array($ch, [
    CURLOPT_POST           =&gt; true,
    CURLOPT_RETURNTRANSFER =&gt; true,
    CURLOPT_HTTPHEADER     =&gt; [
        'Authorization: Bearer ' . getenv('PAYMENTS_SECRET_KEY'),
        'Content-Type: application/json',
        'Idempotency-Key: order_1001',
    ],
    CURLOPT_POSTFIELDS     =&gt; json_encode([
        'amount'     =&gt; '25.00',
        'reference'  =&gt; 'order_1001',
        'return_url' =&gt; 'https://yourshop.com/orders/1001',
    ]),
]);
$payment = json_decode(curl_exec($ch), true);

header('Location: ' . $payment['checkout_url']);</pre>
                    <pre class="code" hidden>use Illuminate\Support\Facades\Http;

$payment = Http::withToken(config('services.payments.key'))
    -&gt;withHeaders(['Idempotency-Key' =&gt; 'order_'.$order-&gt;id])
    -&gt;post('__BASE_URL__/payments', [
        'amount'     =&gt; number_format($order-&gt;total, 2, '.', ''),
        'reference'  =&gt; (string) $order-&gt;id,
        'return_url' =&gt; route('orders.show', $order),
    ])
    -&gt;throw()
    -&gt;json();

return redirect()-&gt;away($payment['checkout_url']);</pre>
                    <pre class="code" hidden>const response = await fetch('__BASE_URL__/payments', {
  method: 'POST',
  headers: {
    Authorization: `Bearer ${process.env.PAYMENTS_SECRET_KEY}`,
    'Content-Type': 'application/json',
    'Idempotency-Key': 'order_1001',
  },
  body: JSON.stringify({ amount: '25.00', reference: 'order_1001' }),
});
const payment = await response.json();
res.redirect(payment.checkout_url);</pre>
                    @endverbatim
                </div>

                <h3>Withdraw your balance</h3>
                @verbatim
                <pre class="code">curl -X POST __BASE_URL__/payouts \
  -H "Authorization: Bearer sk_live_YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "amount": "50.00", "network": "Tron", "address": "TQ7m2wYourTronAddressHere123456789" }'</pre>
                @endverbatim
            </section>
        </div>
    </div>

    <script>
        (() => {
            const base = @json($baseUrl);
            const checkout = @json(url('/checkout/pay_01k8a3k9xw2v7d5j1m4q0r6t8z'));
            document.querySelectorAll('pre.code').forEach((pre) => {
                pre.innerHTML = pre.innerHTML.replaceAll('__BASE_URL__', base).replaceAll('__CHECKOUT_URL__', checkout);
            });

            document.querySelectorAll('[data-tabs]').forEach((tabs) => {
                const buttons = tabs.querySelectorAll('.tab-bar button');
                const panes = tabs.querySelectorAll('pre.code');
                buttons.forEach((button, index) => button.addEventListener('click', () => {
                    buttons.forEach((b, i) => b.classList.toggle('on', i === index));
                    panes.forEach((p, i) => { p.hidden = i !== index; });
                }));
            });
        })();
    </script>
</body>
</html>
