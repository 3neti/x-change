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
        Schema::create('x_change_payment_recovery_reports', function (Blueprint $table) {
            $table->id();
            $table->ulid('reference')->unique();
            $table->unsignedBigInteger('voucher_id')->index();
            $table->text('evidence_ciphertext');
            $table->string('status', 24)->default('open');
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reviewed_by_type')->nullable();
            $table->string('reviewed_by_id')->nullable();
            $table->text('review_note_ciphertext')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('x_change_payment_recovery_reports');
    }
};
