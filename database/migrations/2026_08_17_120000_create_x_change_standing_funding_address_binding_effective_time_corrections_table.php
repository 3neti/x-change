<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_standing_funding_address_binding_effective_time_corrections', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique('xchg_standing_binding_time_reference_unique');
            $table->foreignId('standing_funding_address_binding_revision_id')
                ->unique('xchg_standing_binding_time_revision_unique')
                ->constrained(
                    table: 'x_change_standing_funding_address_binding_revisions',
                    indexName: 'xchg_standing_binding_time_revision_foreign',
                )
                ->restrictOnDelete();
            $table->foreignId('standing_funding_address_binding_migration_id')
                ->unique('xchg_standing_binding_time_migration_unique')
                ->constrained(
                    table: 'x_change_standing_funding_address_binding_migrations',
                    indexName: 'xchg_standing_binding_time_migration_foreign',
                )
                ->restrictOnDelete();
            $table->timestampTz('original_effective_at', 6);
            $table->timestampTz('corrected_effective_at', 6)
                ->index('xchg_standing_binding_time_corrected_index');
            $table->char('approved_evidence_hash', 64)
                ->index('xchg_standing_binding_time_evidence_index');
            $table->char('correction_hash', 64)
                ->unique('xchg_standing_binding_time_hash_unique');
            $table->char('idempotency_key_hash', 64)
                ->unique('xchg_standing_binding_time_idem_unique');
            $table->string('authorization_reference', 191)
                ->index('xchg_standing_binding_time_auth_index');
            $table->morphs('corrected_by', 'xchg_standing_binding_time_corrected_by');
            $table->string('reason', 64);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_standing_funding_address_binding_effective_time_corrections');
    }
};
