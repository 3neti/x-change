<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_campaign_payment_recognitions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->char('recognition_key', 64)->unique();
            $table->foreignId('campaign_payment_qr_binding_id')
                ->constrained('x_change_campaign_payment_qr_bindings')
                ->restrictOnDelete();
            $table->foreignId('canonical_provider_funding_observation_id')
                ->constrained('provider_funding_observations')
                ->restrictOnDelete();
            $table->string('campaign_revision_id');
            $table->string('provider_code', 64)->index();
            $table->char('provider_transaction_key', 64)->index();
            $table->char('provider_operation_key', 64)->nullable()->index();
            $table->char('request_key', 64)->nullable()->index();
            $table->unsignedBigInteger('gross_amount_minor');
            $table->unsignedBigInteger('fee_amount_minor');
            $table->unsignedBigInteger('net_amount_minor');
            $table->char('currency', 3);
            $table->string('provider_status', 64);
            $table->string('settlement_rail', 64)->nullable();
            $table->boolean('destination_verified');
            $table->timestampTz('occurred_at');
            $table->timestampTz('settled_at');
            $table->json('evidence_observation_ids');
            $table->char('evidence_fingerprint', 64);
            $table->char('binding_configuration_hash', 64);
            $table->json('rule_snapshot');
            $table->timestampTz('recognized_at')->index();
            $table->timestampsTz();

            $table->unique(
                ['campaign_payment_qr_binding_id', 'provider_transaction_key'],
                'xchg_campaign_payment_recognition_binding_transaction_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_campaign_payment_recognitions');
    }
};
