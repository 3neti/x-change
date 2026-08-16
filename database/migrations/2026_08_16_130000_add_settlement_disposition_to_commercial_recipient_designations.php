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
            $table->string('settlement_disposition')
                ->default('retain_payable')
                ->after('settlement_designation_reference')
                ->index();
            $table->string('settlement_account_reference')
                ->nullable()
                ->after('settlement_disposition');
        });
    }

    public function down(): void
    {
        Schema::table('x_change_commercial_recipient_designations', function (Blueprint $table): void {
            $table->dropIndex(['settlement_disposition']);
            $table->dropColumn(['settlement_disposition', 'settlement_account_reference']);
        });
    }
};
