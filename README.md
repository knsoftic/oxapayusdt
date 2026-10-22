# PayGate: crypto payment gateway on OxaPay

A Laravel 12 platform that resells one OxaPay account as a payment gateway for many merchants.

- **Admin** (`/admin`): connects the master OxaPay account, sets fees, approves merchants for live mode, reviews and sends withdrawals, sees platform revenue.
- **Merchants** (`/register`, `/dashboard`): get API keys, payment links, a branded checkout, a USD balance (net of fees), webhooks and USDT withdrawals.
- **Developers** (`/docs`): public API reference with code samples, plus a Postman collection at `/downloads/postman_collection.json`.

## How money flows

1. A merchant creates a payment (API or payment link). The customer pays on `/checkout/{id}`.
2. All crypto lands in the platform's OxaPay account (auto-converted to USDT by default).
3. When OxaPay reports `paid`, the merchant's balance gets `amount − pay-in fee`, written to `ledger_entries` exactly once.
4. The merchant withdraws: `amount` leaves the balance, they receive `amount − payout fee` in USDT. Live withdrawals wait for admin approval, then go out through the OxaPay Payout API (or are marked paid by hand).

Test keys (`sk_test_...`) use OxaPay's sandbox invoice page ("Simulate Payment"); test withdrawals complete instantly without sending anything.

## Running locally (XAMPP)

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve
```

Two background processes are needed:

```bash
php artisan queue:work      # delivers webhooks to merchants (with retries)
php artisan schedule:work   # every 5 min: expires abandoned payments, re-checks open ones
```

The first visit to `/login` opens `/setup` to create the admin. Then add the OxaPay keys in **Admin → Settings**.

## Going live

- Host on a domain with HTTPS. OxaPay only sends webhooks to public `https://` URLs (`/oxapay/webhook`).
- Configure SMTP in `.env` (`MAIL_*`) so verification and password-reset emails are delivered.
- Run the queue worker and scheduler under a process manager / cron.
- Add the OxaPay **Payout API key** to send withdrawals automatically.

## Tests

```bash
php artisan test
```

Tests use SQLite in memory and fake every OxaPay call.
