<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_standing_funding_address_binding_heads', function (Blueprint $table): void {
            $table->foreignId('standing_funding_address_id')
                ->primary()
                ->constrained('x_change_standing_funding_addresses')
                ->restrictOnDelete();
            $table->foreignId('current_binding_revision_id')
                ->unique('xchg_standing_binding_head_revision_unique')
                ->constrained(
                    table: 'x_change_standing_funding_address_binding_revisions',
                    indexName: 'xchg_standing_binding_head_revision_foreign',
                )
                ->restrictOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_standing_funding_address_binding_heads');
    }
};
