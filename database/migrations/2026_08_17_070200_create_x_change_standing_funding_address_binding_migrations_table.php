<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_standing_funding_address_binding_migrations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('standing_funding_address_id')
                ->constrained('x_change_standing_funding_addresses')
                ->restrictOnDelete();
            $table->string('status', 32)->default('awaiting_approval')->index();
            $table->text('from_account_reference_ciphertext');
            $table->char('from_account_reference_hash', 64);
            $table->text('to_account_reference_ciphertext');
            $table->char('to_account_reference_hash', 64);
            $table->char('proposed_binding_key', 64);
            $table->text('proposed_destination_snapshot_ciphertext')->nullable();
            $table->char('proposed_destination_fingerprint', 64)->nullable();
            $table->json('evidence_snapshot');
            $table->char('evidence_hash', 64)->index();
            $table->char('idempotency_key_hash', 64)->unique();
            $table->morphs('maker', 'xchg_standing_binding_migration_maker');
            $table->timestampTz('requested_at');
            $table->nullableMorphs('checker', 'xchg_standing_binding_migration_checker');
            $table->string('approval_reference', 191)->nullable()->index();
            $table->timestampTz('approved_at')->nullable();
            $table->nullableMorphs('activated_by', 'xchg_standing_binding_migration_activated_by');
            $table->foreignId('activated_binding_revision_id')
                ->nullable()
                ->unique()
                ->constrained('x_change_standing_funding_address_binding_revisions')
                ->restrictOnDelete();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_standing_funding_address_binding_migrations');
    }
};
