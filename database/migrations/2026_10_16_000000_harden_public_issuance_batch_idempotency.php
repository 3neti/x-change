<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_issuance_batches', function (Blueprint $table): void {
            $table->char('idempotency_key_hash', 64)->nullable()->unique()->after('normalized_fingerprint');
            $table->string('access_token_ciphertext', 512)->nullable()->after('idempotency_key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('x_change_issuance_batches', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key_hash']);
            $table->dropColumn(['idempotency_key_hash', 'access_token_ciphertext']);
        });
    }
};
