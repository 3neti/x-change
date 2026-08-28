<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_payment_attempts', function (Blueprint $table): void {
            $table->longText('merchant_snapshot_ciphertext')->nullable();
            $table->char('merchant_profile_fingerprint', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('x_change_payment_attempts', function (Blueprint $table): void {
            $table->dropIndex(['merchant_profile_fingerprint']);
            $table->dropColumn([
                'merchant_snapshot_ciphertext',
                'merchant_profile_fingerprint',
            ]);
        });
    }
};
