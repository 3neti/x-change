<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('x_change_simulated_funding_transactions', function (Blueprint $table): void {
            $table->dropUnique(['provider_request_id']);
            $table->index('provider_request_id', 'xcsft_request_idx');
            $table->char('simulation_key_hash', 64)->nullable()->unique('xcsft_simulation_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('x_change_simulated_funding_transactions')->select('provider_request_id')
            ->groupBy('provider_request_id')->havingRaw('count(*) > 1')->exists()) {
            throw new RuntimeException('Cannot restore the single-payment simulator while multiple payments exist.');
        }
        Schema::table('x_change_simulated_funding_transactions', function (Blueprint $table): void {
            $table->dropUnique('xcsft_simulation_key_unique');
            $table->dropColumn('simulation_key_hash');
            $table->dropIndex('xcsft_request_idx');
            $table->unique('provider_request_id');
        });
    }
};
