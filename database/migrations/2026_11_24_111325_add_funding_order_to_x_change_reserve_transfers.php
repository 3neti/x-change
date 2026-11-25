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
        Schema::table('x_change_reserve_transfers', function (Blueprint $table) {
            $table->foreignId('funding_order_id')->nullable()->unique()->constrained('x_change_pay_code_issuance_funding_orders')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('x_change_reserve_transfers', function (Blueprint $table) {
            $table->dropForeign(['funding_order_id']);
            $table->dropUnique(['funding_order_id']);
            $table->dropColumn('funding_order_id');
        });
    }
};
