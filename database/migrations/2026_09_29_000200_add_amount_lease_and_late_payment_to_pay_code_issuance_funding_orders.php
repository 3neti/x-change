<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_pay_code_issuance_funding_orders', function (Blueprint $table): void {
            $table->char('amount_lease_active_key', 64)->nullable();
            $table->timestampTz('amount_lease_reserved_at')->nullable();
            $table->timestampTz('amount_lease_reusable_after')->nullable();
            $table->timestampTz('amount_lease_released_at')->nullable();
            $table->timestampTz('late_payment_detected_at')->nullable();
            $table->string('late_payment_disposition', 32)->nullable();

            $table->unique('amount_lease_active_key', 'x_change_issuance_funding_amount_lease_uq');
            $table->index('amount_lease_reusable_after', 'x_change_issuance_funding_lease_reuse_idx');
        });
    }

    public function down(): void
    {
        Schema::table('x_change_pay_code_issuance_funding_orders', function (Blueprint $table): void {
            $table->dropUnique('x_change_issuance_funding_amount_lease_uq');
            $table->dropIndex('x_change_issuance_funding_lease_reuse_idx');
            $table->dropColumn([
                'amount_lease_active_key',
                'amount_lease_reserved_at',
                'amount_lease_reusable_after',
                'amount_lease_released_at',
                'late_payment_detected_at',
                'late_payment_disposition',
            ]);
        });
    }
};
