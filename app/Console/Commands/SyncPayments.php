<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Payout;
use App\Services\PaymentService;
use App\Services\PayoutService;
use Illuminate\Console\Command;

/**
 * Safety net for missed webhooks (or no public URL): expire abandoned
 * payments and ask OxaPay about anything still open.
 */
class SyncPayments extends Command
{
    protected $signature = 'payments:sync';

    protected $description = 'Expire abandoned payments and refresh open payments and payouts from OxaPay';

    public function handle(PaymentService $payments, PayoutService $payouts): int
    {
        $open = Payment::whereNotIn('status', Payment::FINAL_STATUSES)
            ->where('created_at', '>=', now()->subDays(3))
            ->where(fn ($q) => $q->whereNotNull('track_id')->orWhere('expires_at', '<', now()))
            ->orderBy('id')
            ->limit(200)
            ->get();

        foreach ($open as $payment) {
            $payments->sync($payment);
        }

        $processing = Payout::where('status', Payout::STATUS_PROCESSING)->whereNotNull('track_id')->limit(100)->get();

        foreach ($processing as $payout) {
            $payouts->sync($payout);
        }

        $this->info("Checked {$open->count()} payments and {$processing->count()} payouts.");

        return self::SUCCESS;
    }
}
