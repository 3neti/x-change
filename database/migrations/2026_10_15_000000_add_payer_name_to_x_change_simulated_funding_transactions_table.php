<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_simulated_funding_transactions', function (Blueprint $table): void {
            $table->longText('payer_name_ciphertext')->nullable()->after('payer_mobile_hash');
        });
    }

    public function down(): void
    {
        Schema::table('x_change_simulated_funding_transactions', function (Blueprint $table): void {
            $table->dropColumn('payer_name_ciphertext');
        });
    }
};
