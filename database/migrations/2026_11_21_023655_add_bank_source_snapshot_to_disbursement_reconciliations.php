<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disbursement_reconciliations', function (Blueprint $table): void {
            $table->text('bank_source_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('disbursement_reconciliations', function (Blueprint $table): void {
            $table->dropColumn('bank_source_snapshot');
        });
    }
};
