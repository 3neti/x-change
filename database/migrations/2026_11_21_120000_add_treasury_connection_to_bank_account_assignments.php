<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xchange_bank_account_assignments', function (Blueprint $table): void {
            $table->string('treasury_connection_reference', 191)->nullable()->unique();
            $table->string('treasury_inventory_reference', 191)->nullable()->unique();
            $table->string('treasury_resource_reference', 191)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('xchange_bank_account_assignments', function (Blueprint $table): void {
            $table->dropUnique(['treasury_connection_reference']);
            $table->dropUnique(['treasury_inventory_reference']);
            $table->dropUnique(['treasury_resource_reference']);
            $table->dropColumn(['treasury_connection_reference', 'treasury_inventory_reference', 'treasury_resource_reference']);
        });
    }
};
