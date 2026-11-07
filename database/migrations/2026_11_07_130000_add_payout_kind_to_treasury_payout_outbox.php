<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('x_change_treasury_payout_outbox', 'payout_kind')) {
            Schema::table('x_change_treasury_payout_outbox', function (Blueprint $table): void {
                $table->string('payout_kind', 24)->default('treasury');
            });
        }
    }

    public function down(): void {}
};
