<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('x_change_account_funding_codes')) {
            return;
        }

        if (DB::table('x_change_account_funding_codes')->exists()) {
            throw new RuntimeException(
                'Legacy Account Funding Codes must be claimed or cancelled before upgrading to Voucher-backed reviewed funding.',
            );
        }

        Schema::drop('x_change_account_funding_codes');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This guarded retirement is intentionally forward-only.
    }
};
