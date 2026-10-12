<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_checkouts', function (Blueprint $table): void {
            $table->foreignId('partner_api_client_id')->nullable()
                ->constrained('x_change_partner_api_clients')->restrictOnDelete();
            $table->char('api_idempotency_key_hash', 64)->nullable();
            $table->char('api_request_fingerprint', 64)->nullable();
            $table->unique(['partner_api_client_id', 'api_idempotency_key_hash'], 'x_change_checkout_partner_key_unique');
            $table->index(['partner_api_client_id', 'status'], 'x_change_checkout_partner_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('x_change_checkouts', function (Blueprint $table): void {
            $table->dropUnique('x_change_checkout_partner_key_unique');
            $table->dropIndex('x_change_checkout_partner_status_idx');
            $table->dropConstrainedForeignId('partner_api_client_id');
            $table->dropColumn(['api_idempotency_key_hash', 'api_request_fingerprint']);
        });
    }
};
