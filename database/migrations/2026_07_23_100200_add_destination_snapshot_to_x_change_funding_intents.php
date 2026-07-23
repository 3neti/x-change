<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_funding_intents', function (Blueprint $table): void {
            $table->longText('destination_snapshot_ciphertext')->nullable();
            $table->char('destination_fingerprint', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('x_change_funding_intents', function (Blueprint $table): void {
            $table->dropIndex(['destination_fingerprint']);
            $table->dropColumn([
                'destination_snapshot_ciphertext',
                'destination_fingerprint',
            ]);
        });
    }
};
