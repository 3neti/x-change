<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('x_change_treasury_payout_outbox', 'approval_ciphertext')) {
            Schema::table('x_change_treasury_payout_outbox', function (Blueprint $table): void {
                $table->text('approval_ciphertext')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Existing installations may have created the column in the original migration.
    }
};
