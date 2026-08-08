<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_partner_commission_payout_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id');
            $table->foreign('batch_id', 'commission_attempt_batch_fk')
                ->references('id')
                ->on('x_change_partner_commission_payout_batches')
                ->restrictOnDelete();
            $table->foreignId('commercial_partner_destination_revision_id')->nullable();
            $table->foreign('commercial_partner_destination_revision_id', 'commission_attempt_destination_fk')
                ->references('id')
                ->on('x_change_commercial_partner_destination_revisions')
                ->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status')->index();
            $table->string('submission_idempotency_key')->unique();
            $table->string('provider_transaction_id')->nullable()->index();
            $table->string('provider_transaction_uuid')->nullable();
            $table->string('evidence_reference')->nullable()->unique();
            $table->string('rejection_code')->nullable()->index();
            $table->text('rejection_message')->nullable();
            $table->json('metadata');
            $table->timestampTz('submitted_at');
            $table->timestampTz('reconciled_at')->nullable();
            $table->timestampsTz();

            $table->unique(['batch_id', 'attempt_number'], 'commercial_commission_attempt_number_unique');
            $table->index(['batch_id', 'status'], 'commercial_commission_attempt_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_partner_commission_payout_attempts');
    }
};
