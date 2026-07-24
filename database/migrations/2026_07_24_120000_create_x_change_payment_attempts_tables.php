<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('voucher_id')->constrained('vouchers')->restrictOnDelete();
            $table->string('provider_code', 64)->index();
            $table->unsignedBigInteger('expected_amount_minor');
            $table->char('currency', 3);
            $table->string('status', 64)->index();
            $table->unsignedInteger('version')->default(1);
            $table->char('session_key_hash', 64)->index();
            $table->char('idempotency_key_hash', 64)->unique();
            $table->char('idempotency_fingerprint', 64);
            $table->char('provider_reference_hash', 64)->nullable()->index();
            $table->longText('provider_request_id_ciphertext')->nullable();
            $table->longText('funding_address_ciphertext')->nullable();
            $table->char('funding_address_hash', 64)->nullable()->index();
            $table->longText('instructions_ciphertext')->nullable();
            $table->longText('destination_snapshot_ciphertext')->nullable();
            $table->char('destination_fingerprint', 64)->nullable()->index();
            $table->unsignedBigInteger('matched_observation_id')->nullable()->index();
            $table->unsignedBigInteger('voucher_collection_id')->nullable()->unique();
            $table->string('provider_transaction_id', 191)->nullable()->unique();
            $table->timestampTz('instructions_created_at')->nullable();
            $table->timestampTz('last_checked_at')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('x_change_payment_attempt_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_attempt_id')
                ->constrained('x_change_payment_attempts')
                ->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('event_type', 64)->index();
            $table->string('from_status', 64)->nullable();
            $table->string('to_status', 64);
            $table->string('trigger', 32);
            $table->string('evidence_reference', 191)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();

            $table->unique(
                ['payment_attempt_id', 'sequence'],
                'x_change_payment_attempt_event_sequence_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_payment_attempt_events');
        Schema::dropIfExists('x_change_payment_attempts');
    }
};
