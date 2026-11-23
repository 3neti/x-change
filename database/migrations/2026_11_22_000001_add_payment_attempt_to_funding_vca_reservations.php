<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xchange_funding_vca_reservations', function (Blueprint $table): void {
            $table->unsignedBigInteger('payment_attempt_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('xchange_funding_vca_reservations', function (Blueprint $table): void {
            $table->dropUnique(['payment_attempt_id']);
            $table->dropColumn('payment_attempt_id');
        });
    }
};
