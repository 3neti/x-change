<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xchange_funding_vca_reservations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('funding_intent_id')->nullable()->unique();
            $table->string('address_hash', 64)->unique();
            $table->text('address_ciphertext');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xchange_funding_vca_reservations');
    }
};
