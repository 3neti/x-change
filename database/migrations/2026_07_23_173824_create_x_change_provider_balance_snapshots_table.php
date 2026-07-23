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
        Schema::create('x_change_provider_balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('provider_code', 64);
            $table->string('balance_key', 96);
            $table->string('scope_key', 191)->default('global');
            $table->bigInteger('balance_minor')->nullable();
            $table->bigInteger('available_balance_minor')->nullable();
            $table->string('currency', 3)->default('PHP');
            $table->string('account_reference_masked', 191)->nullable();
            $table->timestampTz('provider_as_of')->nullable();
            $table->timestampTz('fetched_at')->nullable()->index();
            $table->string('refresh_status', 32)->default('unavailable');
            $table->text('failure_reason')->nullable();
            $table->timestampTz('last_refresh_failed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider_code', 'balance_key', 'scope_key'],
                'x_change_provider_balance_snapshot_scope_unique',
            );
            $table->index(['provider_code', 'refresh_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('x_change_provider_balance_snapshots');
    }
};
