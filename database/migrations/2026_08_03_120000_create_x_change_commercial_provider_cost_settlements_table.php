<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_commercial_provider_cost_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commercial_sale_id')
                ->constrained('x_change_commercial_sales')
                ->restrictOnDelete();
            $table->foreignId('commercial_allocation_id')
                ->constrained('x_change_commercial_allocations')
                ->restrictOnDelete();
            $table->string('idempotency_key')->unique();
            $table->char('request_hash', 64);
            $table->string('provider')->index();
            $table->string('connection_reference')->index();
            $table->string('evidence_type');
            $table->string('evidence_reference');
            $table->boolean('cash_movement_observed');
            $table->unsignedBigInteger('expected_amount_minor');
            $table->unsignedBigInteger('observed_amount_minor');
            $table->bigInteger('variance_amount_minor');
            $table->char('currency', 3);
            $table->string('status')->index();
            $table->string('position_operation_reference')->nullable()->unique();
            $table->string('inventory_operation_reference')->nullable()->unique();
            $table->json('metadata');
            $table->timestampTz('observed_at');
            $table->timestampTz('settled_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['commercial_allocation_id', 'evidence_reference'],
                'commercial_provider_cost_evidence_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_commercial_provider_cost_settlements');
    }
};
