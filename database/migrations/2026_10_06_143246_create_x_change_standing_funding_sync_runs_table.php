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
        Schema::create('x_change_standing_funding_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('standing_funding_address_id')->constrained('x_change_standing_funding_addresses')->restrictOnDelete();
            $table->string('provider_code', 64)->index();
            $table->unsignedBigInteger('generation');
            $table->ulid('lease_token')->index();
            $table->string('trigger', 32)->index();
            $table->unsignedBigInteger('webhook_receipt_id')->nullable();
            $table->string('status', 24)->default('queued')->index();
            $table->string('failure_classification', 64)->nullable()->index();
            $table->string('failure_type', 128)->nullable();
            $table->json('summary')->nullable();
            $table->timestampTz('started_at', 6)->nullable();
            $table->timestampTz('finished_at', 6)->nullable();
            $table->timestamps();

            $table->index(['provider_code', 'generation', 'status'], 'standing_run_runtime_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('x_change_standing_funding_sync_runs');
    }
};
