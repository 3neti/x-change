<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_stored_value_collections', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('stored_value_holder_binding_id')->constrained('x_change_stored_value_holder_bindings')->restrictOnDelete();
            $table->string('collector_channel', 24);
            $table->string('collector_type', 191);
            $table->string('collector_id', 191);
            $table->char('collector_session_hash', 64)->nullable();
            $table->char('idempotency_key_hash', 64);
            $table->char('request_hash', 64);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 24);
            $table->text('provider_reference_ciphertext')->nullable();
            $table->char('provider_reference_hash', 64)->nullable();
            $table->char('holder_mobile_hash', 64);
            $table->char('proof_reference_hash', 64)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('expires_at', 6);
            $table->timestampTz('verified_at', 6)->nullable();
            $table->timestampTz('committed_at', 6)->nullable();
            $table->unsignedBigInteger('balance_after_minor')->nullable();
            $table->char('treasury_operation_reference_hash', 64)->nullable();
            $table->json('receipt')->nullable();
            $table->timestampsTz(6);
            $table->unique(['collector_channel', 'collector_type', 'collector_id', 'idempotency_key_hash'], 'sv_collection_idempotency_unique');
            $table->index(['stored_value_holder_binding_id', 'status'], 'sv_collection_binding_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_stored_value_collections');
    }
};
