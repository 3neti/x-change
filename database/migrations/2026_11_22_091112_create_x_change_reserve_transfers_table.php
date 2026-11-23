<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_reserve_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 36)->unique();
            $table->string('provider', 32);
            $table->char('idempotency_hash', 64)->unique();
            $table->char('request_fingerprint', 64);
            $table->char('submission_fingerprint', 64)->nullable();
            $table->text('request_ciphertext');
            $table->text('bank_evidence_ciphertext')->nullable();
            $table->text('treasury_binding_ciphertext')->nullable();
            $table->text('issuance_snapshot_ciphertext')->nullable();
            $table->char('issuance_snapshot_fingerprint', 64)->nullable();
            $table->string('source_treasury_connection');
            $table->string('reserve_treasury_connection');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('status', 32)->index();
            $table->nullableMorphs('owner');
            $table->unsignedBigInteger('bank_account_link_id')->nullable();
            $table->string('hold_operation_reference')->nullable()->unique();
            $table->string('fee_hold_operation_reference')->nullable()->unique();
            $table->unsignedBigInteger('fee_minor')->default(0);
            $table->string('provider_transaction_id')->nullable();
            $table->unsignedBigInteger('voucher_id')->nullable()->unique();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('held_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('provider_settled_at')->nullable();
            $table->timestamp('treasury_posted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_reserve_transfers');
    }
};
