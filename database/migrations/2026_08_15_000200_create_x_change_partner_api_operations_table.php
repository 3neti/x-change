<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_partner_api_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_api_client_id')
                ->constrained('x_change_partner_api_clients')
                ->restrictOnDelete();
            $table->string('operation', 80);
            $table->string('idempotency_key', 160);
            $table->string('correlation_id', 160)->nullable();
            $table->string('subject_reference', 120)->nullable();
            $table->unsignedBigInteger('principal_minor');
            $table->string('currency', 3);
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->unique(['partner_api_client_id', 'idempotency_key'], 'partner_api_operation_idempotency_unique');
            $table->index(['partner_api_client_id', 'operation', 'occurred_at'], 'partner_api_operation_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_partner_api_operations');
    }
};
