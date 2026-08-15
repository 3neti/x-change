<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_treasury_account_grants', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->string('status', 40)->index();
            $table->morphs('recipient');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('connection_reference');
            $table->string('purpose', 255);
            $table->boolean('test_allocation')->default(false)->index();
            $table->char('request_hash', 64);
            $table->string('idempotency_reference_hash')->unique();
            $table->morphs('maker');
            $table->timestampTz('submitted_at');
            $table->nullableMorphs('checker');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->string('source_position_reference')->nullable();
            $table->string('destination_position_reference')->nullable();
            $table->string('operation_reference')->nullable()->unique();
            $table->timestampTz('executed_at')->nullable();
            $table->timestamps();

            $table->index(['connection_reference', 'currency', 'status'], 'x_change_treasury_account_grant_connection_status');
            $table->index(['recipient_type', 'recipient_id', 'created_at'], 'x_change_treasury_account_grant_recipient_history');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_treasury_account_grants');
    }
};
