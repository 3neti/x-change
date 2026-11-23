<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xchange_voucher_bank_sources', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('voucher_id')->unique();
            $table->text('source_ciphertext');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xchange_voucher_bank_sources');
    }
};
