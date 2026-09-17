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
        Schema::create('x_change_campaign_display_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('lead_campaign_id')->constrained('x_change_lead_campaigns')->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->text('entry_token');
            $table->string('browser_hash', 64)->nullable();
            $table->unsignedBigInteger('voucher_id')->nullable()->unique();
            $table->unsignedBigInteger('payment_attempt_id')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('x_change_campaign_display_sessions');
    }
};
