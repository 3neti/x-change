<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_campaign_payment_evidence_quarantines', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->char('quarantine_key', 64)->unique();
            $table->foreignId('campaign_payment_qr_binding_id')
                ->constrained('x_change_campaign_payment_qr_bindings')
                ->restrictOnDelete();
            $table->foreignId('provider_funding_observation_id')
                ->constrained('provider_funding_observations')
                ->restrictOnDelete();
            $table->string('provider_code', 64)->index();
            $table->char('provider_transaction_key', 64)->index();
            $table->string('reason_code', 64)->index();
            $table->string('reason_detail', 96)->nullable();
            $table->json('evidence_observation_ids');
            $table->char('evidence_fingerprint', 64)->index();
            $table->timestampTz('opened_at')->index();
            $table->timestampsTz();

            $table->index(
                ['campaign_payment_qr_binding_id', 'opened_at'],
                'xchg_campaign_payment_quarantine_binding_opened_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_campaign_payment_evidence_quarantines');
    }
};
