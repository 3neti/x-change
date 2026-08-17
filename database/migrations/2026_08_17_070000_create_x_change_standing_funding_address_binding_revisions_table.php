<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_standing_funding_address_binding_revisions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('standing_funding_address_id')
                ->constrained('x_change_standing_funding_addresses')
                ->restrictOnDelete();
            $table->unsignedInteger('binding_version');
            $table->foreignId('previous_binding_revision_id')
                ->nullable()
                ->constrained('x_change_standing_funding_address_binding_revisions')
                ->restrictOnDelete();
            $table->text('account_reference_ciphertext');
            $table->char('account_reference_hash', 64)->index();
            $table->char('binding_key', 64)->unique();
            $table->text('destination_snapshot_ciphertext')->nullable();
            $table->char('destination_fingerprint', 64)->nullable()->index();
            $table->string('reason', 64);
            $table->json('evidence_snapshot');
            $table->char('evidence_hash', 64)->index();
            $table->string('approval_reference', 191)->nullable()->index();
            $table->nullableMorphs('activated_by', 'xchg_standing_binding_revision_activated_by');
            $table->timestampTz('effective_at', 6)->index();
            $table->timestampsTz();

            $table->unique(
                ['standing_funding_address_id', 'binding_version'],
                'xchg_standing_binding_revision_version_unique',
            );
            $table->unique(
                ['standing_funding_address_id', 'effective_at'],
                'xchg_standing_binding_revision_effective_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('x_change_standing_funding_address_binding_revisions');
        Schema::enableForeignKeyConstraints();
    }
};
