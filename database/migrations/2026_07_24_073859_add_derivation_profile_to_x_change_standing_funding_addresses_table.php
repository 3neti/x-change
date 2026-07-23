<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('x_change_standing_funding_addresses', function (Blueprint $table): void {
            $table->string('derivation_scheme', 64)->nullable()->after('version')->index();
            $table->string('derivation_key_id', 64)->nullable()->after('derivation_scheme');
            $table->unsignedSmallInteger('derivation_counter')->default(0)->after('derivation_key_id');
            $table->unsignedSmallInteger('reference_length')->nullable()->after('derivation_counter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('x_change_standing_funding_addresses', function (Blueprint $table): void {
            $table->dropIndex(['derivation_scheme']);
            $table->dropColumn([
                'derivation_scheme',
                'derivation_key_id',
                'derivation_counter',
                'reference_length',
            ]);
        });
    }
};
