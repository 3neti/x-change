<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_claim_payout_locality_contexts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('voucher_id');
            $table->string('claim_key_hash', 64);
            $table->text('evidence');
            $table->timestamps();
            $table->unique(['voucher_id', 'claim_key_hash'], 'claim_locality_voucher_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_claim_payout_locality_contexts');
    }
};
