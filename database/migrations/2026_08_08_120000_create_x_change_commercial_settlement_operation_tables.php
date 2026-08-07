<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_commercial_provider_cost_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->string('provider')->index();
            $table->string('connection_reference')->index();
            $table->char('currency', 3);
            $table->string('evidence_type');
            $table->string('evidence_reference');
            $table->unsignedBigInteger('expected_amount_minor');
            $table->unsignedBigInteger('observed_amount_minor');
            $table->bigInteger('variance_amount_minor');
            $table->string('status')->index();
            $table->string('idempotency_key')->unique();
            $table->char('request_hash', 64);
            $table->nullableMorphs('recorded_by', 'commercial_provider_cost_recorded_by');
            $table->json('metadata');
            $table->timestampTz('period_started_at');
            $table->timestampTz('period_ended_at');
            $table->timestampTz('observed_at');
            $table->timestampTz('settled_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['provider', 'connection_reference', 'evidence_reference'],
                'commercial_provider_cost_batch_evidence_unique',
            );
            $table->index(
                ['provider', 'connection_reference', 'currency', 'period_started_at', 'period_ended_at'],
                'commercial_provider_cost_batch_period_index',
            );
        });

        Schema::create('x_change_commercial_provider_cost_batch_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')
                ->constrained('x_change_commercial_provider_cost_batches')
                ->restrictOnDelete();
            $table->foreignId('commercial_allocation_id')
                ->constrained('x_change_commercial_allocations')
                ->restrictOnDelete();
            $table->foreignId('settlement_id')
                ->nullable()
                ->constrained('x_change_commercial_provider_cost_settlements')
                ->restrictOnDelete();
            $table->unsignedBigInteger('expected_amount_minor');
            $table->timestampsTz();

            $table->unique('commercial_allocation_id', 'commercial_provider_cost_batch_allocation_unique');
            $table->unique(['batch_id', 'commercial_allocation_id'], 'commercial_provider_cost_batch_line_unique');
        });

        Schema::create('x_change_partner_commission_payout_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->string('partner_reference')->index();
            $table->string('provider')->index();
            $table->string('connection_reference')->index();
            $table->string('position_reference');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status')->index();
            $table->text('destination')->nullable();
            $table->char('destination_hash', 64);
            $table->string('destination_summary');
            $table->string('request_idempotency_key')->unique();
            $table->char('request_hash', 64);
            $table->nullableMorphs('maker', 'commercial_commission_batch_maker');
            $table->nullableMorphs('checker', 'commercial_commission_batch_checker');
            $table->string('approval_reference')->nullable()->unique();
            $table->string('submission_idempotency_key')->nullable()->unique();
            $table->string('provider_transaction_id')->nullable()->index();
            $table->string('provider_transaction_uuid')->nullable();
            $table->string('evidence_reference')->nullable()->unique();
            $table->string('position_operation_reference')->nullable()->unique();
            $table->string('inventory_operation_reference')->nullable()->unique();
            $table->json('metadata');
            $table->timestampTz('period_started_at');
            $table->timestampTz('period_ended_at');
            $table->timestampTz('requested_at');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampsTz();

            $table->index(
                ['partner_reference', 'provider', 'connection_reference', 'currency', 'status'],
                'commercial_commission_batch_payable_index',
            );
        });

        Schema::create('x_change_partner_commission_payout_batch_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')
                ->constrained('x_change_partner_commission_payout_batches')
                ->restrictOnDelete();
            $table->foreignId('commercial_allocation_id')
                ->constrained('x_change_commercial_allocations')
                ->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->timestampsTz();

            $table->unique('commercial_allocation_id', 'commercial_commission_batch_allocation_unique');
            $table->unique(['batch_id', 'commercial_allocation_id'], 'commercial_commission_batch_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_partner_commission_payout_batch_lines');
        Schema::dropIfExists('x_change_partner_commission_payout_batches');
        Schema::dropIfExists('x_change_commercial_provider_cost_batch_lines');
        Schema::dropIfExists('x_change_commercial_provider_cost_batches');
    }
};
