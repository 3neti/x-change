<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_payout_destination_revisions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->unsignedBigInteger('voucher_id')->index();
            $table->foreignId('voucher_claim_id')
                ->constrained('voucher_claims')
                ->restrictOnDelete();
            $table->foreignId('rejected_reconciliation_id')
                ->constrained('disbursement_reconciliations')
                ->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('bank_code', 64);
            $table->text('account_number_ciphertext');
            $table->char('account_number_hash', 64);
            $table->string('account_number_masked', 64);
            $table->text('mobile_ciphertext')->nullable();
            $table->char('mobile_hash', 64)->nullable();
            $table->string('validation_status', 64)->index();
            $table->json('validation_metadata')->nullable();
            $table->nullableMorphs('requested_by');
            $table->timestampTz('recorded_at');
            $table->timestamps();

            $table->unique(
                ['voucher_id', 'version'],
                'x_change_payout_destination_revision_version_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_payout_destination_revisions');
    }
};
