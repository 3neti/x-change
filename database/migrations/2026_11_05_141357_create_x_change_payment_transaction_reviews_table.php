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
        Schema::create('x_change_payment_transaction_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('observed_payment_transaction_id')->unique('xc_ptr_observed_unique')->constrained('x_change_observed_payment_transactions', indexName: 'xc_ptr_observed_fk')->restrictOnDelete();
            $table->foreignId('provider_funding_observation_id')->nullable()->constrained('provider_funding_observations', indexName: 'xc_ptr_observation_fk')->restrictOnDelete();
            $table->string('reason_code', 80);
            $table->string('status', 32)->default('open');
            $table->string('resolved_by_type')->nullable();
            $table->string('resolved_by_id')->nullable();
            $table->string('resolution_code', 80)->nullable();
            $table->text('resolution_note_ciphertext')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolution_funding_intent_id')->nullable()->constrained('x_change_funding_intents', indexName: 'xc_ptr_funding_fk')->restrictOnDelete();
            $table->foreignId('resolution_voucher_collection_id')->nullable()->constrained('voucher_collections', indexName: 'xc_ptr_collection_fk')->restrictOnDelete();
            $table->foreignId('resolution_payment_attempt_id')->nullable()->constrained('x_change_payment_attempts', indexName: 'xc_ptr_attempt_fk')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('x_change_payment_review_attempts', function (Blueprint $table): void {
            $table->foreignId('review_id')->constrained('x_change_payment_transaction_reviews')->restrictOnDelete();
            $table->foreignId('payment_attempt_id')->constrained('x_change_payment_attempts')->restrictOnDelete();
            $table->primary(['review_id', 'payment_attempt_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('x_change_payment_review_attempts');
        Schema::dropIfExists('x_change_payment_transaction_reviews');
    }
};
