<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_commercial_recipient_designations', function (Blueprint $table): void {
            $table->string('settlement_principal_reference')
                ->nullable()
                ->after('settlement_account_reference');
        });
    }

    public function down(): void
    {
        Schema::table('x_change_commercial_recipient_designations', function (Blueprint $table): void {
            $table->dropColumn('settlement_principal_reference');
        });
    }
};
