<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_voucher_slice_executions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('voucher_id')->constrained('vouchers')->restrictOnDelete();
            $table->foreignId('voucher_claim_id')->nullable()->unique()->constrained('voucher_claims')->restrictOnDelete();
            $table->char('plan_fingerprint', 64);
            $table->char('idempotency_key_hash', 64);
            $table->char('request_fingerprint', 64);
            $table->string('provider_operation_reference', 191)->unique();
            $table->unsignedInteger('claim_number');
            $table->string('status', 32)->default('reserved');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->unsignedInteger('version')->default(1);
            $table->json('metadata')->nullable();
            $table->timestampTz('reserved_at', 6);
            $table->timestampTz('executing_at', 6)->nullable();
            $table->timestampTz('provider_confirmed_at', 6)->nullable();
            $table->timestampTz('settled_at', 6)->nullable();
            $table->timestampTz('failed_at', 6)->nullable();
            $table->timestampTz('indeterminate_at', 6)->nullable();
            $table->timestampsTz(6);

            $table->unique(['voucher_id', 'idempotency_key_hash'], 'slice_exec_voucher_idempotency_uq');
            $table->unique(['voucher_id', 'claim_number'], 'slice_exec_voucher_claim_number_uq');
            $table->index(['voucher_id', 'status'], 'slice_exec_voucher_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_voucher_slice_executions');
    }
};
