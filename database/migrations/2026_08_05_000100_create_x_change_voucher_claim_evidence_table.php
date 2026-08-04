<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_voucher_claim_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('voucher_claim_id')
                ->constrained('voucher_claims')
                ->restrictOnDelete();
            $table->foreignId('voucher_id')
                ->constrained('vouchers')
                ->restrictOnDelete();
            $table->string('requirement_key', 64);
            $table->string('kind', 32)->index();
            $table->string('status', 32)->index();
            $table->string('summary', 255)->nullable();
            $table->longText('payload')->nullable();
            $table->string('artifact_disk', 64)->nullable();
            $table->string('artifact_path', 512)->nullable();
            $table->string('mime_type', 127)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->timestampTz('captured_at');
            $table->timestampTz('verified_at')->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['voucher_claim_id', 'requirement_key'],
                'x_change_claim_evidence_requirement_unique',
            );
            $table->index(
                ['voucher_id', 'voucher_claim_id'],
                'x_change_claim_evidence_voucher_claim_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_voucher_claim_evidence');
    }
};
