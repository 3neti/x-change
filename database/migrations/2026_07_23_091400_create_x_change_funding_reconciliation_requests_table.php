<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_funding_reconciliation_requests', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->char('request_key', 64)->unique();
            $table->foreignId('funding_suspense_case_id')
                ->constrained('x_change_funding_suspense_cases')
                ->restrictOnDelete();
            $table->string('action', 64)->index();
            $table->string('status', 32)->default('pending_approval')->index();
            $table->json('payload')->nullable();
            $table->string('requested_by_type', 191);
            $table->string('requested_by_id', 191)->index();
            $table->timestampTz('requested_at');
            $table->string('approved_by_type', 191)->nullable();
            $table->string('approved_by_id', 191)->nullable()->index();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('executed_at')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_funding_reconciliation_requests');
    }
};
