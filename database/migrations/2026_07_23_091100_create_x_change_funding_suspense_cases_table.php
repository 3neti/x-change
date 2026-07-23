<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_funding_suspense_cases', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->char('case_key', 64)->unique();
            $table->foreignId('funding_intent_id')
                ->nullable()
                ->constrained('x_change_funding_intents')
                ->restrictOnDelete();
            $table->foreignId('provider_funding_observation_id')
                ->nullable()
                ->constrained('provider_funding_observations')
                ->restrictOnDelete();
            $table->foreignId('webhook_receipt_id')
                ->nullable()
                ->constrained('webhook_receipts')
                ->restrictOnDelete();
            $table->string('provider_code', 64)->index();
            $table->string('reason_code', 64)->index();
            $table->string('status', 32)->default('open')->index();
            $table->json('details')->nullable();
            $table->timestampTz('opened_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->string('resolved_by_type', 191)->nullable();
            $table->string('resolved_by_id', 191)->nullable()->index();
            $table->string('resolution_code', 64)->nullable();
            $table->json('resolution')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_funding_suspense_cases');
    }
};
