<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_completion_pay_code_issuances', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->char('issuance_key', 64)->unique();
            $table->char('instruction_hash', 64);
            $table->foreignId('provisional_coverage_id');
            $table->unique('provisional_coverage_id', 'xchg_completion_coverage_unique');
            $table->foreign('provisional_coverage_id', 'xchg_completion_coverage_foreign')
                ->references('id')
                ->on('x_change_provisional_coverages')
                ->restrictOnDelete();
            $table->foreignId('envelope_id');
            $table->unique('envelope_id', 'xchg_completion_envelope_unique');
            $table->foreign('envelope_id', 'xchg_completion_envelope_foreign')
                ->references('id')
                ->on('envelopes')
                ->restrictOnDelete();
            $table->foreignId('voucher_id');
            $table->unique('voucher_id', 'xchg_completion_voucher_unique');
            $table->foreign('voucher_id', 'xchg_completion_voucher_foreign')
                ->references('id')
                ->on('vouchers')
                ->restrictOnDelete();
            $table->string('issuer_type');
            $table->string('issuer_id');
            $table->string('driver_id', 128);
            $table->string('driver_version', 32);
            $table->json('requirements_snapshot');
            $table->timestampTz('issued_at')->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_completion_pay_code_issuances');
    }
};
