<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xchange_bank_account_identities', function (Blueprint $table): void {
            $table->id();
            $table->morphs('owner');
            $table->string('provider');
            $table->char('account_fingerprint', 64)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xchange_bank_account_identities');
    }
};
