<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_payout_submission_claims', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('voucher_id');
            $table->string('reference_hash', 64);
            $table->string('claim_type', 32);
            $table->timestamp('created_at');
            $table->unique(['voucher_id', 'reference_hash', 'claim_type'], 'payout_submission_claim_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_payout_submission_claims');
    }
};
