<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_system_account_funding_pay_code_issuances', function (Blueprint $table): void {
            $table->string('authorization_reference', 191)
                ->nullable()
                ->after('evidence_reference')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('x_change_system_account_funding_pay_code_issuances', function (Blueprint $table): void {
            $table->dropIndex(['authorization_reference']);
            $table->dropColumn('authorization_reference');
        });
    }
};
