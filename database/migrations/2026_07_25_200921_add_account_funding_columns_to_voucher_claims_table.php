<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_claims', function (Blueprint $table) {
            $table->string('settlement_mode')
                ->default('provider_payout')
                ->after('claim_type')
                ->index();
            $table->nullableMorphs('claimant');
            $table->string('account_funding_scope')
                ->nullable()
                ->unique();
            $table->string('treasury_operation_reference')
                ->nullable()
                ->unique();
        });
    }

    public function down(): void
    {
        Schema::table('voucher_claims', function (Blueprint $table) {
            $table->dropUnique(['treasury_operation_reference']);
            $table->dropUnique(['account_funding_scope']);
            $table->dropMorphs('claimant');
            $table->dropIndex(['settlement_mode']);
            $table->dropColumn('settlement_mode');
        });
    }
};
