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
        Schema::create('x_change_standing_funding_address_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('standing_funding_address_id')->unique()->constrained('x_change_standing_funding_addresses')->cascadeOnDelete();
            $table->string('provider_code', 64)->index();
            $table->string('status', 24)->default('idle')->index();
            $table->unsignedBigInteger('generation')->default(1);
            $table->ulid('lease_token')->nullable()->unique();
            $table->timestampTz('lease_expires_at', 6)->nullable()->index();
            $table->timestampTz('next_eligible_at', 6)->nullable()->index();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->string('last_failure_classification', 64)->nullable();
            $table->string('last_failure_type', 128)->nullable();
            $table->text('quarantine_reason')->nullable();
            $table->timestampTz('quarantined_at', 6)->nullable();
            $table->timestampTz('ambiguous_at', 6)->nullable();
            $table->timestamps();

            $table->index(['provider_code', 'status', 'next_eligible_at'], 'standing_state_admission_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('x_change_standing_funding_address_states');
    }
};
