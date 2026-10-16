<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('x_change_checkout_refund_cases')) {
            return;
        }

        Schema::table('x_change_checkout_refund_cases', function (Blueprint $table): void {
            $table->string('disposition', 32)->default('external_return')->after('status');
            $table->timestampTz('hold_until')->nullable()->after('disposition');
            $table->timestampTz('eligible_at')->nullable()->after('hold_until');
            $table->unsignedBigInteger('recipient_contact_id')->nullable()->after('eligible_at');
            $table->unsignedBigInteger('refund_pay_code_id')->nullable()->after('recipient_contact_id');
            $table->char('idempotency_key_hash', 64)->nullable()->unique()->after('refund_pay_code_id');
            $table->json('metadata')->nullable()->after('idempotency_key_hash');

            $table->index(['status', 'hold_until'], 'x_change_refund_status_hold_idx');
            $table->index('recipient_contact_id', 'x_change_refund_recipient_contact_idx');
            $table->index('refund_pay_code_id', 'x_change_refund_pay_code_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('x_change_checkout_refund_cases')) {
            return;
        }

        Schema::table('x_change_checkout_refund_cases', function (Blueprint $table): void {
            $table->dropIndex('x_change_refund_status_hold_idx');
            $table->dropIndex('x_change_refund_recipient_contact_idx');
            $table->dropIndex('x_change_refund_pay_code_idx');
            $table->dropUnique(['idempotency_key_hash']);
            $table->dropColumn([
                'disposition',
                'hold_until',
                'eligible_at',
                'recipient_contact_id',
                'refund_pay_code_id',
                'idempotency_key_hash',
                'metadata',
            ]);
        });
    }
};
