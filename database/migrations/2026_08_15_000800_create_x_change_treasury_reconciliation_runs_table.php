<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_treasury_reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->string('status', 40)->index();
            $table->string('connection_reference');
            $table->string('provider');
            $table->char('currency', 3);
            $table->string('purpose', 255);
            $table->char('request_hash', 64);
            $table->char('idempotency_reference_hash', 64)->unique();
            $table->morphs('maker', 'xctrr_maker_idx');
            $table->timestampTz('submitted_at');
            $table->nullableMorphs('checker', 'xctrr_checker_idx');
            $table->timestampTz('approved_at')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestampTz('last_attempt_at')->nullable();
            $table->unsignedBigInteger('provider_balance_minor')->nullable();
            $table->unsignedBigInteger('inventory_balance_minor')->nullable();
            $table->unsignedBigInteger('position_balance_minor')->nullable();
            $table->bigInteger('difference_minor')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->timestampTz('observed_at')->nullable();
            $table->string('inventory_operation_reference')->nullable();
            $table->string('position_operation_reference')->nullable();
            $table->string('reason', 191)->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['connection_reference', 'currency', 'status'],
                'xctrr_connection_currency_status_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_treasury_reconciliation_runs');
    }
};
