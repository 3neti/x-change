<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_funding_evidence_claim_supersessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funding_evidence_claim_id')
                ->unique('x_change_funding_evidence_supersession_claim_unique')
                ->constrained('x_change_funding_evidence_claims')
                ->restrictOnDelete();
            $table->foreignId('from_funding_intent_id')
                ->constrained('x_change_funding_intents')
                ->restrictOnDelete();
            $table->foreignId('to_funding_intent_id')
                ->unique('x_change_funding_evidence_supersession_target_unique')
                ->constrained('x_change_funding_intents')
                ->restrictOnDelete();
            $table->foreignId('provider_funding_observation_id')
                ->unique('x_change_funding_evidence_supersession_observation_unique')
                ->constrained('provider_funding_observations')
                ->restrictOnDelete();
            $table->foreignId('funding_reconciliation_request_id')
                ->unique('x_change_funding_evidence_supersession_request_unique')
                ->constrained('x_change_funding_reconciliation_requests')
                ->restrictOnDelete();
            $table->foreignId('source_suspense_case_id')
                ->constrained('x_change_funding_suspense_cases')
                ->restrictOnDelete();
            $table->foreignId('target_suspense_case_id')
                ->constrained('x_change_funding_suspense_cases')
                ->restrictOnDelete();
            $table->string('reason_code', 64);
            $table->string('approved_by_type', 191);
            $table->string('approved_by_id', 191)->index();
            $table->timestampTz('superseded_at');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_funding_evidence_claim_supersessions');
    }
};
