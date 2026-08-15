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
            $table->dropUnique('partner_api_operation_idempotency_unique');
            $table->unique(
                ['partner_api_client_id', 'operation', 'idempotency_key'],
                'partner_api_operation_scope_idempotency_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('x_change_partner_api_operations', function (Blueprint $table): void {
            $table->dropUnique('partner_api_operation_scope_idempotency_unique');
            $table->unique(
                ['partner_api_client_id', 'idempotency_key'],
                'partner_api_operation_idempotency_unique',
            );
        });
    }
};
