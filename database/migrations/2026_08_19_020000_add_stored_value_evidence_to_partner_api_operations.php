<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_partner_api_operations', function (Blueprint $table): void {
            $table->string('reference', 26)->nullable()->unique('partner_api_operation_reference_unique');
            $table->char('request_hash', 64)->nullable()->index('partner_api_operation_request_hash_index');
            $table->unsignedBigInteger('balance_after_minor')->nullable();
            $table->char('authority_reference_hash', 64)->nullable();
            $table->char('treasury_operation_reference_hash', 64)->nullable();
            $table->json('response_snapshot')->nullable();
            $table->index(
                ['partner_api_client_id', 'operation', 'subject_reference', 'occurred_at'],
                'partner_api_operation_subject_period_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('x_change_partner_api_operations', function (Blueprint $table): void {
            $table->dropIndex('partner_api_operation_subject_period_index');
            $table->dropIndex('partner_api_operation_request_hash_index');
            $table->dropUnique('partner_api_operation_reference_unique');
            $table->dropColumn([
                'reference',
                'request_hash',
                'balance_after_minor',
                'authority_reference_hash',
                'treasury_operation_reference_hash',
                'response_snapshot',
            ]);
        });
    }
};
