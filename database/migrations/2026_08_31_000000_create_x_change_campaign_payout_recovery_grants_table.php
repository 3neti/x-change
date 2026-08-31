<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_campaign_payout_recovery_grants', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('voucher_id')
                ->constrained('vouchers')
                ->restrictOnDelete();
            $table->foreignId('campaign_worksheet_fulfillment_id')
                ->constrained('campaign_worksheet_fulfillments')
                ->restrictOnDelete();
            $table->foreignId('rejected_reconciliation_id')
                ->unique('campaign_payout_recovery_rejection_unique')
                ->constrained('disbursement_reconciliations')
                ->restrictOnDelete();
            $table->char('mobile_hash', 64);
            $table->string('provider', 80);
            $table->string('purpose', 120);
            $table->text('provider_challenge_reference_ciphertext')->nullable();
            $table->char('provider_challenge_reference_hash', 64)->nullable()->unique(
                'campaign_payout_recovery_provider_challenge_unique',
            );
            $table->string('status', 32)->default('available');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('expires_at', 6);
            $table->timestampTz('otp_expires_at', 6)->nullable();
            $table->timestampTz('provider_verified_at', 6)->nullable();
            $table->timestampTz('verified_at', 6)->nullable();
            $table->timestampTz('submitting_at', 6)->nullable();
            $table->timestampTz('consumed_at', 6)->nullable();
            $table->timestampsTz(6);

            $table->index(
                ['campaign_worksheet_fulfillment_id', 'status'],
                'campaign_payout_recovery_fulfillment_status_idx',
            );
            $table->index('expires_at', 'campaign_payout_recovery_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_campaign_payout_recovery_grants');
    }
};
