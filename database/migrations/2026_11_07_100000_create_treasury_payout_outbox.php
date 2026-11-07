<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_treasury_payout_outbox', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('voucher_id')->unique();
            $table->text('claim_key_ciphertext')->nullable();
            $table->text('approval_ciphertext')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->string('lease_token', 36)->nullable();
            $table->timestamp('lease_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_treasury_payout_outbox');
    }
};
