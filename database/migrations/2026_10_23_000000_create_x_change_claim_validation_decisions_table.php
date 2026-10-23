<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_claim_validation_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('voucher_id')->constrained('vouchers')->restrictOnDelete();
            $table->foreignId('voucher_claim_id')->nullable()->constrained('voucher_claims')->nullOnDelete();
            $table->char('decision_key', 64)->unique();
            $table->char('instruction_fingerprint', 64);
            $table->char('input_fingerprint', 64);
            $table->string('status', 16);
            $table->json('issues');
            $table->string('journal_status', 16)->default('pending');
            $table->unsignedInteger('journal_attempts')->default(0);
            $table->timestampTz('occurred_at', 6);
            $table->timestampTz('journaled_at', 6)->nullable();
            $table->string('journal_error', 255)->nullable();
            $table->timestampsTz(6);

            $table->index(['journal_status', 'occurred_at', 'id'], 'claim_validation_journal_delivery_idx');
            $table->index(['voucher_id', 'occurred_at', 'id'], 'claim_validation_voucher_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_claim_validation_decisions');
    }
};
