<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xchange_bank_identity_key_guard', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->char('key_verifier', 64);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xchange_bank_identity_key_guard');
    }
};
