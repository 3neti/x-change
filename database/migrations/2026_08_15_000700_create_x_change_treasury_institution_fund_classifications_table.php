<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_treasury_institution_fund_classifications', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->string('status', 40)->index();
            $table->string('evidence_operation_reference')->unique();
            $table->string('evidence_reference');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('connection_reference');
            $table->string('ownership_basis', 255);
            $table->char('request_hash', 64);
            $table->string('idempotency_reference_hash')->unique();
            $table->morphs('maker', 'xctifc_maker_idx');
            $table->timestampTz('submitted_at');
            $table->nullableMorphs('checker', 'xctifc_checker_idx');
            $table->timestampTz('approved_at')->nullable();
            $table->string('source_position_reference')->nullable();
            $table->string('destination_position_reference')->nullable();
            $table->string('operation_reference')->nullable()->unique();
            $table->timestampTz('executed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['connection_reference', 'currency', 'status'],
                'x_change_treasury_institution_fund_connection_status',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_treasury_institution_fund_classifications');
    }
};
