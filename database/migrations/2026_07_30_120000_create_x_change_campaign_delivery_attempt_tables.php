<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_campaign_delivery_attempts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('campaign_worksheet_authorization_id')
                ->constrained('campaign_worksheet_authorizations')
                ->restrictOnDelete();
            $table->foreignId('campaign_worksheet_fulfillment_id')
                ->nullable()
                ->constrained('campaign_worksheet_fulfillments')
                ->restrictOnDelete();
            $table->string('channel', 24)->index();
            $table->unsignedInteger('attempt_number');
            $table->char('idempotency_key_hash', 64)->unique();
            $table->ulid('retry_of_reference')->nullable()->index();
            $table->nullableMorphs('requested_by');
            $table->char('recipient_route_hash', 64)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestampTz('requested_at');
            $table->timestamps();

            $table->unique(
                ['campaign_worksheet_authorization_id', 'channel', 'attempt_number'],
                'x_change_campaign_delivery_attempt_number_unique',
            );
        });

        Schema::create('x_change_campaign_delivery_attempt_events', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('campaign_delivery_attempt_id')
                ->constrained('x_change_campaign_delivery_attempts')
                ->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('event_type', 32)->index();
            $table->string('provider_status', 64)->nullable();
            $table->string('provider_delivery_reference', 191)->nullable()->index();
            $table->string('safe_error_code', 64)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();

            $table->unique(
                ['campaign_delivery_attempt_id', 'sequence'],
                'x_change_campaign_delivery_attempt_event_sequence_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_campaign_delivery_attempt_events');
        Schema::dropIfExists('x_change_campaign_delivery_attempts');
    }
};
