<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_commercial_allocations', function (Blueprint $table): void {
            $table->foreignId('commercial_partner_id')->nullable()->after('commercial_sale_id');
            $table->foreign('commercial_partner_id', 'commercial_allocation_partner_fk')
                ->references('id')
                ->on('x_change_commercial_partners')
                ->restrictOnDelete();
            $table->foreignId('commercial_partner_revision_id')->nullable()->after('commercial_partner_id');
            $table->foreign('commercial_partner_revision_id', 'commercial_allocation_partner_revision_fk')
                ->references('id')
                ->on('x_change_commercial_partner_revisions')
                ->restrictOnDelete();
            $table->string('legacy_partner_reference')->nullable()->index();
        });

        Schema::table('x_change_partner_commission_payout_batches', function (Blueprint $table): void {
            $table->foreignId('commercial_partner_id')->nullable()->after('partner_reference');
            $table->foreign('commercial_partner_id', 'commission_batch_partner_fk')
                ->references('id')
                ->on('x_change_commercial_partners')
                ->restrictOnDelete();
            $table->foreignId('commercial_partner_revision_id')->nullable()->after('commercial_partner_id');
            $table->foreign('commercial_partner_revision_id', 'commission_batch_partner_revision_fk')
                ->references('id')
                ->on('x_change_commercial_partner_revisions')
                ->restrictOnDelete();
            $table->foreignId('commercial_partner_destination_revision_id')->nullable()->after('commercial_partner_revision_id');
            $table->foreign('commercial_partner_destination_revision_id', 'commission_batch_partner_destination_fk')
                ->references('id')
                ->on('x_change_commercial_partner_destination_revisions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('x_change_partner_commission_payout_batches', function (Blueprint $table): void {
            $table->dropForeign(['commercial_partner_destination_revision_id']);
            $table->dropForeign(['commercial_partner_revision_id']);
            $table->dropForeign(['commercial_partner_id']);
            $table->dropColumn([
                'commercial_partner_destination_revision_id',
                'commercial_partner_revision_id',
                'commercial_partner_id',
            ]);
        });

        Schema::table('x_change_commercial_allocations', function (Blueprint $table): void {
            $table->dropIndex('x_change_commercial_allocations_legacy_partner_reference_index');
            $table->dropColumn('legacy_partner_reference');
            $table->dropForeign(['commercial_partner_revision_id']);
            $table->dropForeign(['commercial_partner_id']);
            $table->dropColumn(['commercial_partner_revision_id', 'commercial_partner_id']);
        });
    }
};
