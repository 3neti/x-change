<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xchange_provider_account_links', function (Blueprint $table): void {
            $table->longText('routing_profile_ciphertext')->nullable();
            $table->char('routing_fingerprint', 64)->nullable()->index();
            $table->string('display_reference', 191)->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('disabled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('xchange_provider_account_links', function (Blueprint $table): void {
            $table->dropIndex(['routing_fingerprint']);
            $table->dropColumn([
                'routing_profile_ciphertext',
                'routing_fingerprint',
                'display_reference',
                'verified_at',
                'activated_at',
                'disabled_at',
            ]);
        });
    }
};
