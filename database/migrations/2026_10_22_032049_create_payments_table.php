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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('merchant_id')->constrained();
            $table->foreignId('payment_link_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode', 4);
            $table->string('status', 30)->default('pending')->index();

            $table->decimal('amount', 18, 2);
            $table->string('currency', 3)->default('USD');
            // Fee is fixed when the payment is created, so the merchant knows the net up front.
            $table->decimal('fee', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2)->default(0);

            $table->string('reference', 100)->nullable();
            $table->string('description')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('callback_url', 500)->nullable();
            $table->string('return_url', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->string('idempotency_key', 100)->nullable();

            // OxaPay side: "invoice" (hosted page) or "white_label" (our checkout).
            $table->string('type', 20)->nullable();
            $table->string('track_id')->nullable()->unique();
            $table->string('payment_url')->nullable();
            $table->string('pay_currency', 20)->nullable();
            // Kept as a string so small crypto amounts never lose precision.
            $table->string('pay_amount', 50)->nullable();
            $table->string('network')->nullable();
            $table->string('address')->nullable();
            $table->string('memo')->nullable();
            $table->text('qr_code')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'mode', 'idempotency_key']);
            $table->index(['merchant_id', 'mode', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
