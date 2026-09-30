<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_funding_evidence_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funding_intent_id')
                ->constrained('x_change_funding_intents')->restrictOnDelete();
            $table->string('provider_code', 64);
            $table->char('provider_transaction_hash', 64);
            $table->foreignId('provider_funding_observation_id')
                ->constrained('provider_funding_observations')->restrictOnDelete();
            $table->timestampTz('claimed_at');
            $table->timestampsTz();

            $table->unique('funding_intent_id', 'x_change_funding_evidence_intent_unique');
            $table->unique(
                ['provider_code', 'provider_transaction_hash'],
                'x_change_funding_evidence_provider_tx_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_funding_evidence_claims');
    }
};
