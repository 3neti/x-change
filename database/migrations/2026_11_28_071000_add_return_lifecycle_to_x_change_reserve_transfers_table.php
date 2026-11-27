<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_reserve_transfers', function (Blueprint $table): void {
            $table->string('transfer_kind', 32)->default('issuance')->index()->after('provider');
            $table->foreignId('origin_reserve_transfer_id')
                ->nullable()
                ->after('voucher_id')
                ->constrained('x_change_reserve_transfers')
                ->restrictOnDelete();
            $table->string('terminal_reason', 32)->nullable()->after('origin_reserve_transfer_id');
        });
    }

    public function down(): void
    {
        Schema::table('x_change_reserve_transfers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('origin_reserve_transfer_id');
            $table->dropIndex(['transfer_kind']);
            $table->dropColumn(['transfer_kind', 'terminal_reason']);
        });
    }
};
