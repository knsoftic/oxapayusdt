<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name');
            $table->string('website')->nullable();
            $table->string('brand_color', 7)->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('live_enabled')->default(false);
            $table->timestamp('live_requested_at')->nullable();

            // Per-merchant fee overrides; null means "use the platform default".
            $table->decimal('fee_payin_percent', 5, 2)->nullable();
            $table->decimal('fee_payin_fixed', 10, 2)->nullable();
            $table->decimal('fee_payout_fixed', 10, 2)->nullable();

            $table->string('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable();

            // Cached running balances in USD; the ledger_entries table is the source of truth.
            $table->decimal('live_balance', 18, 2)->default(0);
            $table->decimal('test_balance', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
