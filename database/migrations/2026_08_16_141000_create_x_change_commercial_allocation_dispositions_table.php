<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_commercial_allocation_dispositions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commercial_allocation_id')
                ->unique()
                ->constrained('x_change_commercial_allocations')
                ->restrictOnDelete();
            $table->string('disposition')->index();
            $table->string('status')->default('committed')->index();
            $table->string('designation_reference')->index();
            $table->string('authority_reference')->index();
            $table->char('authority_hash', 64)->index();
            $table->char('account_reference_hash', 64)->nullable()->index();
            $table->char('principal_reference_hash', 64)->nullable()->index();
            $table->string('source_position_reference');
            $table->string('destination_position_reference')->nullable();
            $table->string('treasury_operation_reference')->nullable()->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->timestampTz('committed_at')->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_commercial_allocation_dispositions');
    }
};
