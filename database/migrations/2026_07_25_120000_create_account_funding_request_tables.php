<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_funding_requests', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->string('account_reference', 191)->index();
            $table->string('requester_type', 191);
            $table->string('requester_id', 191)->index();
            $table->string('funding_type', 64)->index();
            $table->unsignedBigInteger('requested_value_minor');
            $table->unsignedBigInteger('approved_value_minor')->nullable();
            $table->char('currency', 3);
            $table->string('status', 64)->index();
            $table->unsignedInteger('version')->default(1);
            $table->char('idempotency_key_hash', 64)->unique();
            $table->char('idempotency_fingerprint', 64);
            $table->text('description');
            $table->longText('external_reference_ciphertext')->nullable();
            $table->date('occurred_on')->nullable();
            $table->longText('requester_notes_ciphertext')->nullable();
            $table->longText('review_notes_ciphertext')->nullable();
            $table->string('evidence_reference', 191)->nullable()->index();
            $table->string('connection_reference', 191)->nullable()->index();
            $table->string('reviewed_by_type', 191)->nullable();
            $table->string('reviewed_by_id', 191)->nullable()->index();
            $table->string('approved_by_type', 191)->nullable();
            $table->string('approved_by_id', 191)->nullable()->index();
            $table->timestampTz('submitted_at');
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampTz('withdrawn_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('x_change_funding_request_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funding_request_id')
                ->constrained('x_change_funding_requests')
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
                ['funding_request_id', 'sequence'],
                'x_change_funding_request_event_sequence_unique',
            );
        });

        Schema::create('x_change_account_funding_codes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('funding_request_id')
                ->unique()
                ->constrained('x_change_funding_requests')
                ->restrictOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->longText('code_ciphertext');
            $table->string('code_last_four', 4);
            $table->string('recipient_type', 191);
            $table->string('recipient_id', 191)->index();
            $table->string('account_reference', 191)->index();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('connection_reference', 191);
            $table->string('source_position_reference', 191);
            $table->string('reserve_position_reference', 191);
            $table->string('destination_position_reference', 191);
            $table->string('reservation_operation_reference', 191)->unique();
            $table->string('claim_operation_reference', 191)->unique();
            $table->string('status', 64)->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('issued_at');
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('expires_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('x_change_funding_request_notices', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('funding_request_id')
                ->constrained('x_change_funding_requests')
                ->restrictOnDelete();
            $table->string('recipient_type', 191);
            $table->string('recipient_id', 191)->index();
            $table->string('notice_type', 64)->index();
            $table->string('title', 191);
            $table->text('message');
            $table->json('action')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_funding_request_notices');
        Schema::dropIfExists('x_change_account_funding_codes');
        Schema::dropIfExists('x_change_funding_request_events');
        Schema::dropIfExists('x_change_funding_requests');
    }
};
