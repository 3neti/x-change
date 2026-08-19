<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateClaimNumber = DB::table('voucher_claims')
            ->select(['voucher_id', 'claim_number'])
            ->whereNotNull('claim_number')
            ->groupBy(['voucher_id', 'claim_number'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        $duplicateIdempotency = DB::table('voucher_claims')
            ->select(['voucher_id', 'idempotency_key'])
            ->whereNotNull('idempotency_key')
            ->groupBy(['voucher_id', 'idempotency_key'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateClaimNumber || $duplicateIdempotency) {
            throw new \RuntimeException('Duplicate voucher claim execution identities must be reconciled before enabling durable slice execution.');
        }

        Schema::table('voucher_claims', function (Blueprint $table): void {
            $table->unique(['voucher_id', 'claim_number'], 'voucher_claims_voucher_number_uq');
            $table->unique(['voucher_id', 'idempotency_key'], 'voucher_claims_voucher_idempotency_uq');
        });
    }

    public function down(): void
    {
        Schema::table('voucher_claims', function (Blueprint $table): void {
            $table->dropUnique('voucher_claims_voucher_number_uq');
            $table->dropUnique('voucher_claims_voucher_idempotency_uq');
        });
    }
};
