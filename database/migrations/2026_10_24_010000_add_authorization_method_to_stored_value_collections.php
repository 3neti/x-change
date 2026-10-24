<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_stored_value_collections', function (Blueprint $table): void {
            $table->string('authorization_method', 24)->default('holder_otp');
        });
    }

    public function down(): void
    {
        Schema::table('x_change_stored_value_collections', function (Blueprint $table): void {
            $table->dropColumn('authorization_method');
        });
    }
};
