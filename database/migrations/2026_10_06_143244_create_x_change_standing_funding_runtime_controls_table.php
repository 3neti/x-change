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
        Schema::create('x_change_standing_funding_runtime_controls', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_code', 64)->unique();
            $table->string('mode', 24)->default('disabled')->index();
            $table->unsignedBigInteger('generation')->default(1);
            $table->foreignId('canary_address_id')->nullable()->constrained('x_change_standing_funding_addresses')->nullOnDelete();
            $table->unsignedInteger('batch_limit')->default(1);
            $table->unsignedInteger('backlog_ceiling')->default(25);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestampTz('circuit_open_until', 6)->nullable();
            $table->string('last_transition', 80)->default('runtime_initialized');
            $table->string('actor_type', 64)->nullable();
            $table->string('actor_id', 128)->nullable();
            $table->timestampTz('transitioned_at', 6);
            $table->timestamps();

            $table->index(['mode', 'circuit_open_until'], 'standing_runtime_mode_circuit_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('x_change_standing_funding_runtime_controls');
    }
};
