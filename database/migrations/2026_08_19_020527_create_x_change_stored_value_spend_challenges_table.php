<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_stored_value_spend_challenges', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('partner_api_client_id')
                ->constrained('x_change_partner_api_clients')
                ->restrictOnDelete();
            $table->foreignId('stored_value_holder_binding_id')
                ->constrained('x_change_stored_value_holder_bindings')
                ->restrictOnDelete();
            $table->foreignId('consumed_partner_api_operation_id')
                ->nullable()
                ->constrained('x_change_partner_api_operations')
                ->restrictOnDelete();
            $table->char('idempotency_key_hash', 64);
            $table->char('request_hash', 64);
            $table->char('mobile_hash', 64);
            $table->string('provider', 80);
            $table->string('purpose', 120);
            $table->text('provider_reference_ciphertext')->nullable();
            $table->char('provider_reference_hash', 64)->nullable()->unique();
            $table->string('status', 32)->default('delivery_pending');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->char('proof_reference_hash', 64)->nullable();
            $table->timestampTz('expires_at', 6);
            $table->timestampTz('provider_verified_at', 6)->nullable();
            $table->timestampTz('verified_at', 6)->nullable();
            $table->timestampTz('consumed_at', 6)->nullable();
            $table->timestampsTz(6);

            $table->unique(
                ['partner_api_client_id', 'idempotency_key_hash'],
                'stored_value_spend_challenge_idempotency_unique',
            );
            $table->index(
                ['partner_api_client_id', 'stored_value_holder_binding_id', 'status'],
                'stored_value_spend_challenge_client_binding_status_idx',
            );
            $table->index('expires_at', 'stored_value_spend_challenge_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_stored_value_spend_challenges');
    }
};
