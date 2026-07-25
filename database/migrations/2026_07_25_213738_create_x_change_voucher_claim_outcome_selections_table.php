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
        Schema::create('x_change_voucher_claim_outcome_selections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('voucher_id')
                ->unique()
                ->constrained('vouchers')
                ->cascadeOnDelete();
            $table->string('outcome_key')->index();
            $table->string('policy_profile');
            $table->string('selection_mode');
            $table->nullableMorphs('claimant');
            $table->string('claimant_reference')->nullable();
            $table->timestamp('selected_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('x_change_voucher_claim_outcome_selections');
    }
};
