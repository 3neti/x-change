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
        Schema::table('x_change_funding_requests', function (Blueprint $table): void {
            $table->foreignId('voucher_id')
                ->nullable()
                ->unique()
                ->after('reference')
                ->constrained('vouchers')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('x_change_funding_requests', function (Blueprint $table): void {
            $table->dropUnique(['voucher_id']);
            $table->dropForeign(['voucher_id']);
            $table->dropColumn('voucher_id');
        });
    }
};
