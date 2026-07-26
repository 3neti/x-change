<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_collections', function (Blueprint $table): void {
            $table->string('idempotency_fingerprint', 64)->nullable();
            $table->string('execution_driver')->nullable()->index();
            $table->string('treasury_operation_reference')->nullable()->unique();
            $table->unique(
                ['voucher_id', 'idempotency_key'],
                'voucher_collections_voucher_idempotency_unique',
            );
            $table->unique(
                ['provider', 'provider_transaction_id'],
                'voucher_collections_provider_transaction_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('voucher_collections', function (Blueprint $table): void {
            $table->dropUnique(
                'voucher_collections_voucher_idempotency_unique',
            );
            $table->dropUnique(
                'voucher_collections_provider_transaction_unique',
            );
            $table->dropUnique(
                'voucher_collections_treasury_operation_reference_unique',
            );
            $table->dropIndex(
                'voucher_collections_execution_driver_index',
            );
            $table->dropColumn([
                'idempotency_fingerprint',
                'execution_driver',
                'treasury_operation_reference',
            ]);
        });
    }
};
