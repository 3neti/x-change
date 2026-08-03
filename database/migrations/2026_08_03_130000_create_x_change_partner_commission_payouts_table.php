<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_partner_commission_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commercial_sale_id')
                ->constrained('x_change_commercial_sales')
                ->restrictOnDelete();
            $table->foreignId('commercial_allocation_id')
                ->unique()
                ->constrained('x_change_commercial_allocations')
                ->restrictOnDelete();
            $table->string('partner_reference')->index();
            $table->string('provider');
            $table->string('connection_reference')->index();
            $table->string('position_reference');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status')->index();
            $table->string('request_idempotency_key')->unique();
            $table->char('request_hash', 64);
            $table->string('maker_reference');
            $table->string('checker_reference')->nullable();
            $table->string('approval_reference')->nullable()->unique();
            $table->string('settlement_idempotency_key')->nullable()->unique();
            $table->char('settlement_hash', 64)->nullable();
            $table->string('evidence_reference')->nullable()->unique();
            $table->string('position_operation_reference')->nullable()->unique();
            $table->string('inventory_operation_reference')->nullable()->unique();
            $table->json('metadata');
            $table->timestampTz('requested_at');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_partner_commission_payouts');
    }
};
