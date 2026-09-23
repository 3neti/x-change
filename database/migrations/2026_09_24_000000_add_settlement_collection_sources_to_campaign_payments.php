<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_campaign_payment_sources', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('endpoint_campaign_id')->constrained('x_change_lead_campaigns')->restrictOnDelete();
            $table->foreignId('voucher_collection_id')->unique()->constrained('voucher_collections')->restrictOnDelete();
            $table->foreignId('payment_attempt_id')->unique()->constrained('x_change_payment_attempts')->restrictOnDelete();
            $table->string('source_kind', 64);
            $table->string('campaign_revision_id', 80);
            $table->string('provider_code', 64);
            $table->char('currency', 3);
            $table->char('configuration_hash', 64)->unique();
            $table->timestampsTz();
        });

        Schema::table('x_change_campaign_payment_recognitions', function (Blueprint $table): void {
            $table->dropForeign(['campaign_payment_qr_binding_id']);
        });
        Schema::table('x_change_campaign_payment_recognitions', function (Blueprint $table): void {
            $table->unsignedBigInteger('campaign_payment_qr_binding_id')->nullable()->change();
        });
        Schema::table('x_change_campaign_payment_recognitions', function (Blueprint $table): void {
            $table->foreign('campaign_payment_qr_binding_id')->references('id')->on('x_change_campaign_payment_qr_bindings')->restrictOnDelete();
        });
        Schema::table('x_change_campaign_payment_recognitions', function (Blueprint $table): void {
            $table->foreignId('campaign_payment_source_id')->nullable()->after('campaign_payment_qr_binding_id')
                ->constrained('x_change_campaign_payment_sources')->restrictOnDelete();
            $table->unique(['campaign_payment_source_id', 'provider_transaction_key'], 'xchg_campaign_payment_source_transaction_unique');
        });

        Schema::table('x_change_provisional_coverages', function (Blueprint $table): void {
            $table->dropForeign(['campaign_payment_qr_binding_id']);
        });
        Schema::table('x_change_provisional_coverages', function (Blueprint $table): void {
            $table->unsignedBigInteger('campaign_payment_qr_binding_id')->nullable()->change();
        });
        Schema::table('x_change_provisional_coverages', function (Blueprint $table): void {
            $table->foreign('campaign_payment_qr_binding_id')->references('id')->on('x_change_campaign_payment_qr_bindings')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $sourceRecognitionIds = DB::table('x_change_campaign_payment_recognitions')
            ->whereNotNull('campaign_payment_source_id')
            ->pluck('id');
        $sourceCoverageIds = DB::table('x_change_provisional_coverages')
            ->whereIn('campaign_payment_recognition_id', $sourceRecognitionIds)
            ->pluck('id');
        $sourceIssuanceIds = DB::table('x_change_completion_pay_code_issuances')
            ->whereIn('provisional_coverage_id', $sourceCoverageIds)->pluck('id');
        $sourceProjectionIds = DB::table('x_change_completion_claim_evidence_projections')
            ->whereIn('completion_pay_code_issuance_id', $sourceIssuanceIds)->pluck('id');
        $sourceRequestIds = DB::table('x_change_policy_completion_requests')
            ->whereIn('completion_claim_evidence_projection_id', $sourceProjectionIds)->pluck('id');
        DB::table('x_change_policy_completion_outcomes')->whereIn('policy_completion_request_id', $sourceRequestIds)->delete();
        DB::table('x_change_policy_completion_requests')->whereIn('id', $sourceRequestIds)->delete();
        DB::table('x_change_completion_claim_evidence_projections')->whereIn('id', $sourceProjectionIds)->delete();
        DB::table('x_change_completion_pay_code_issuances')->whereIn('id', $sourceIssuanceIds)->delete();
        DB::table('x_change_provisional_coverages')
            ->whereIn('campaign_payment_recognition_id', $sourceRecognitionIds)
            ->delete();
        DB::table('x_change_campaign_payment_recognitions')
            ->whereNotNull('campaign_payment_source_id')
            ->delete();

        Schema::table('x_change_provisional_coverages', function (Blueprint $table): void {
            $table->dropForeign(['campaign_payment_qr_binding_id']);
        });
        Schema::table('x_change_provisional_coverages', function (Blueprint $table): void {
            $table->unsignedBigInteger('campaign_payment_qr_binding_id')->nullable(false)->change();
        });
        Schema::table('x_change_provisional_coverages', function (Blueprint $table): void {
            $table->foreign('campaign_payment_qr_binding_id')->references('id')->on('x_change_campaign_payment_qr_bindings')->restrictOnDelete();
        });
        Schema::table('x_change_campaign_payment_recognitions', function (Blueprint $table): void {
            $table->dropUnique('xchg_campaign_payment_source_transaction_unique');
            $table->dropConstrainedForeignId('campaign_payment_source_id');
            $table->dropForeign(['campaign_payment_qr_binding_id']);
        });
        Schema::table('x_change_campaign_payment_recognitions', function (Blueprint $table): void {
            $table->unsignedBigInteger('campaign_payment_qr_binding_id')->nullable(false)->change();
        });
        Schema::table('x_change_campaign_payment_recognitions', function (Blueprint $table): void {
            $table->foreign('campaign_payment_qr_binding_id')->references('id')->on('x_change_campaign_payment_qr_bindings')->restrictOnDelete();
        });
        Schema::dropIfExists('x_change_campaign_payment_sources');
    }
};
