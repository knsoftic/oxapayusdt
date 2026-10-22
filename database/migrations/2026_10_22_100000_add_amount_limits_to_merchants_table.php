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
        Schema::table('merchants', function (Blueprint $table) {
            // Per-merchant amount limits in USD; null means "use the platform default".
            $table->decimal('limit_payin_min', 18, 2)->nullable()->after('fee_payout_fixed');
            $table->decimal('limit_payin_max', 18, 2)->nullable()->after('limit_payin_min');
            $table->decimal('limit_payout_min', 18, 2)->nullable()->after('limit_payin_max');
            $table->decimal('limit_payout_max', 18, 2)->nullable()->after('limit_payout_min');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn(['limit_payin_min', 'limit_payin_max', 'limit_payout_min', 'limit_payout_max']);
        });
    }
};
