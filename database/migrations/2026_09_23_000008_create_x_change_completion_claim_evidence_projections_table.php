<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_completion_claim_evidence_projections', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('completion_pay_code_issuance_id');
            $table->unique('completion_pay_code_issuance_id', 'xchg_claim_projection_issuance_unique');
            $table->foreign('completion_pay_code_issuance_id', 'xchg_claim_projection_issuance_foreign')
                ->references('id')
                ->on('x_change_completion_pay_code_issuances')
                ->restrictOnDelete();
            $table->foreignId('voucher_claim_id');
            $table->unique('voucher_claim_id', 'xchg_claim_projection_claim_unique');
            $table->foreign('voucher_claim_id', 'xchg_claim_projection_claim_foreign')
                ->references('id')
                ->on('voucher_claims')
                ->restrictOnDelete();
            $table->foreignId('envelope_id')->constrained('envelopes')->restrictOnDelete();
            $table->foreignId('envelope_payload_version_id');
            $table->unique('envelope_payload_version_id', 'xchg_claim_projection_payload_unique');
            $table->foreign('envelope_payload_version_id', 'xchg_claim_projection_payload_foreign')
                ->references('id')
                ->on('envelope_payload_versions')
                ->restrictOnDelete();
            $table->char('manifest_hash', 64);
            $table->char('projection_hash', 64);
            $table->text('source_snapshot');
            $table->timestampTz('projected_at')->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_completion_claim_evidence_projections');
    }
};
