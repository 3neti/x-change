<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_pay_code_issuance_funding_orders', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->string('account_reference', 191);
            $table->string('issuer_type', 191);
            $table->string('issuer_id', 191);
            $table->string('provider', 64);
            $table->string('connection_reference', 191);
            $table->string('funding_basis', 32);
            $table->longText('instructions_ciphertext');
            $table->char('instructions_fingerprint', 64);
            $table->longText('pricing_snapshot_ciphertext');
            $table->char('pricing_fingerprint', 64);
            $table->unsignedBigInteger('required_amount_minor');
            $table->unsignedBigInteger('reserved_client_funds_minor')->default(0);
            $table->unsignedBigInteger('on_demand_amount_minor');
            $table->unsignedBigInteger('reconciliation_adjustment_minor')->default(0);
            $table->unsignedBigInteger('expected_payment_minor');
            $table->char('currency', 3);
            $table->string('status', 32);
            $table->unsignedBigInteger('version')->default(1);
            $table->char('idempotency_key_hash', 64)->unique();
            $table->char('idempotency_fingerprint', 64);
            $table->foreignId('funding_intent_id')->nullable()->unique()
                ->constrained('x_change_funding_intents')->restrictOnDelete();
            $table->string('treasury_hold_reference', 191)->nullable()->unique();
            $table->foreignId('voucher_id')->nullable()->unique()
                ->constrained('vouchers')->restrictOnDelete();
            $table->timestampTz('payer_acknowledged_at')->nullable();
            $table->timestampTz('funded_at')->nullable();
            $table->timestampTz('issuing_at')->nullable();
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->timestampTz('attention_at')->nullable();
            $table->timestampTz('expires_at');
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['account_reference', 'status'], 'x_change_issuance_funding_account_status_idx');
            $table->index(['status', 'expires_at'], 'x_change_issuance_funding_status_expiry_idx');
            $table->index(['issuer_type', 'issuer_id'], 'x_change_issuance_funding_issuer_idx');
        });

        Schema::create('x_change_pay_code_issuance_funding_order_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funding_order_id')
                ->constrained('x_change_pay_code_issuance_funding_orders')->restrictOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->string('event_type', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('actor_type', 191);
            $table->string('actor_id', 191);
            $table->json('metadata')->nullable();
            $table->timestampTz('occurred_at');

            $table->unique(['funding_order_id', 'sequence'], 'x_change_issuance_funding_events_sequence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_pay_code_issuance_funding_order_events');
        Schema::dropIfExists('x_change_pay_code_issuance_funding_orders');
    }
};
