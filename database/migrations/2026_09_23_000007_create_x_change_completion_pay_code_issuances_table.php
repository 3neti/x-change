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
            $table->foreignId('provisional_coverage_id')->unique()->constrained('x_change_provisional_coverages')->restrictOnDelete();
            $table->foreignId('envelope_id')->unique()->constrained('envelopes')->restrictOnDelete();
            $table->foreignId('voucher_id')->unique()->constrained('vouchers')->restrictOnDelete();
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
