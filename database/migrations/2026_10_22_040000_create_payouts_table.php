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
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('merchant_id')->constrained();
            $table->string('mode', 4);
            $table->string('status', 20)->default('pending')->index();

            // amount leaves the balance; fee is kept by the platform; net_amount is sent.
            $table->decimal('amount', 18, 2);
            $table->decimal('fee', 18, 2);
            $table->decimal('net_amount', 18, 2);
            $table->string('currency', 10)->default('USDT');
            $table->string('network', 50);
            $table->string('address');
            $table->string('memo')->nullable();

            $table->string('track_id')->nullable()->unique();
            $table->string('tx_hash')->nullable();
            $table->text('note')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'mode', 'idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
