<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_account_funding_receipts', function (Blueprint $table): void {
            $table->foreignId('standing_funding_address_binding_revision_id')
                ->nullable()
                ->after('standing_funding_address_id')
                ->constrained('x_change_standing_funding_address_binding_revisions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('x_change_account_funding_receipts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('standing_funding_address_binding_revision_id');
        });
    }
};
