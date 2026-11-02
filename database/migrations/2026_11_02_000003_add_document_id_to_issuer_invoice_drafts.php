<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_issuer_invoice_drafts', function (Blueprint $table): void {
            $table->string('document_id', 26)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('x_change_issuer_invoice_drafts', function (Blueprint $table): void {
            $table->dropUnique(['document_id']);
            $table->dropColumn('document_id');
        });
    }
};
