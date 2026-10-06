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
        Schema::create('x_change_standing_funding_runtime_outbox', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->string('event_type', 100)->index();
            $table->string('aggregate_type', 64);
            $table->string('aggregate_id', 128);
            $table->unsignedBigInteger('generation')->nullable();
            $table->json('payload');
            $table->string('journal_status', 24)->default('pending')->index();
            $table->string('broadcast_status', 24)->default('pending')->index();
            $table->unsignedInteger('journal_attempts')->default(0);
            $table->unsignedInteger('broadcast_attempts')->default(0);
            $table->text('journal_error')->nullable();
            $table->text('broadcast_error')->nullable();
            $table->timestampTz('occurred_at', 6);
            $table->timestampTz('journaled_at', 6)->nullable();
            $table->timestampTz('broadcast_at', 6)->nullable();
            $table->timestamps();

            $table->unique(['event_type', 'aggregate_type', 'aggregate_id', 'generation'], 'standing_outbox_event_identity');
            $table->index(['journal_status', 'broadcast_status', 'occurred_at'], 'standing_outbox_delivery_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('x_change_standing_funding_runtime_outbox');
    }
};
