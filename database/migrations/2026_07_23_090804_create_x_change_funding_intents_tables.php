<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_funding_intents', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->string('account_reference', 191)->index();
            $table->string('provider_code', 64)->index();
            $table->unsignedBigInteger('expected_amount_minor');
            $table->char('currency', 3);
            $table->string('status', 64)->index();
            $table->unsignedInteger('version')->default(1);
            $table->char('idempotency_key_hash', 64)->unique();
            $table->char('idempotency_fingerprint', 64);
            $table->string('created_by_type', 191);
            $table->string('created_by_id', 191)->index();
            $table->string('provider_reference', 191)->nullable()->index();
            $table->string('provider_request_id', 191)->nullable()->index();
            $table->longText('funding_address_ciphertext')->nullable();
            $table->char('funding_address_hash', 64)->nullable()->index();
            $table->longText('instructions_ciphertext')->nullable();
            $table->unsignedBigInteger('matched_observation_id')->nullable()->index();
            $table->string('provider_transaction_id', 191)->nullable()->index();
            $table->timestampTz('instructions_created_at')->nullable();
            $table->timestampTz('evidence_received_at')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->timestampTz('reversed_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('x_change_funding_intent_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funding_intent_id')
                ->constrained('x_change_funding_intents')
                ->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('event_type', 64)->index();
            $table->string('from_status', 64)->nullable();
            $table->string('to_status', 64);
            $table->string('actor_type', 191);
            $table->string('actor_id', 191)->index();
            $table->string('evidence_reference', 191)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();

            $table->unique(
                ['funding_intent_id', 'sequence'],
                'x_change_funding_intent_event_sequence_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_funding_intent_events');
        Schema::dropIfExists('x_change_funding_intents');
    }
};
