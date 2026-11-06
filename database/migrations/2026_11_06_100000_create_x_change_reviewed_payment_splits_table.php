<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('x_change_reviewed_payment_splits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->unique('xc_rps_review_unique')->constrained('x_change_payment_transaction_reviews')->restrictOnDelete();
            $table->foreignId('voucher_id')->constrained('vouchers')->restrictOnDelete();
            $table->foreignId('provider_observation_id')->constrained('provider_funding_observations')->restrictOnDelete();
            $table->string('provider_code', 64);
            $table->char('provider_transaction_hash', 64);
            $table->unique(['provider_code', 'provider_transaction_hash'], 'xc_rps_evidence_unique');
            $table->unsignedBigInteger('gross_amount_minor');
            $table->unsignedBigInteger('collection_amount_minor');
            $table->unsignedBigInteger('excess_amount_minor');
            $table->char('currency', 3);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('x_change_reviewed_payment_splits');
    }
};
