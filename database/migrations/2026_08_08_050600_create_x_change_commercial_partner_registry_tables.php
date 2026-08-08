<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_commercial_partners', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->string('display_name');
            $table->string('status')->index();
            $table->nullableMorphs('created_by', 'commercial_partner_created_by');
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->timestampTz('retired_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'activated_at'], 'commercial_partner_status_index');
        });

        Schema::create('x_change_commercial_partner_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commercial_partner_id')
                ->constrained('x_change_commercial_partners')
                ->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->index();
            $table->string('display_name');
            $table->string('legal_name')->nullable();
            $table->string('external_reference')->nullable();
            $table->string('attribution_basis');
            $table->string('authorization_reference');
            $table->json('terms');
            $table->char('snapshot_hash', 64);
            $table->nullableMorphs('maker', 'commercial_partner_revision_maker');
            $table->nullableMorphs('checker', 'commercial_partner_revision_checker');
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('effective_at')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampsTz();

            $table->unique(['commercial_partner_id', 'version'], 'commercial_partner_revision_version_unique');
            $table->unique(['commercial_partner_id', 'snapshot_hash'], 'commercial_partner_revision_snapshot_unique');
            $table->index(['commercial_partner_id', 'status', 'effective_at'], 'commercial_partner_revision_effective_index');
        });

        Schema::create('x_change_commercial_partner_destination_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commercial_partner_id')
                ->constrained('x_change_commercial_partners')
                ->restrictOnDelete();
            $table->foreignId('commercial_partner_revision_id')
                ->constrained('x_change_commercial_partner_revisions')
                ->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->index();
            $table->string('provider')->index();
            $table->string('connection_reference')->index();
            $table->char('currency', 3);
            $table->text('destination');
            $table->char('destination_hash', 64);
            $table->string('destination_summary');
            $table->nullableMorphs('maker', 'commercial_partner_destination_maker');
            $table->nullableMorphs('checker', 'commercial_partner_destination_checker');
            $table->string('authorization_reference');
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('effective_at')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampsTz();

            $table->unique(['commercial_partner_id', 'version'], 'commercial_partner_destination_version_unique');
            $table->unique(['commercial_partner_id', 'destination_hash'], 'commercial_partner_destination_hash_unique');
            $table->index(
                ['commercial_partner_id', 'status', 'provider', 'connection_reference', 'currency'],
                'commercial_partner_destination_active_index',
            );
        });

        Schema::create('x_change_commercial_partner_legacy_mappings', function (Blueprint $table): void {
            $table->id();
            $table->string('legacy_partner_reference')->unique();
            $table->foreignId('commercial_partner_id')
                ->constrained('x_change_commercial_partners')
                ->restrictOnDelete();
            $table->foreignId('commercial_partner_revision_id')
                ->constrained('x_change_commercial_partner_revisions')
                ->restrictOnDelete();
            $table->nullableMorphs('mapped_by', 'commercial_partner_legacy_mapping_operator');
            $table->string('authorization_reference');
            $table->timestampTz('mapped_at');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_commercial_partner_legacy_mappings');
        Schema::dropIfExists('x_change_commercial_partner_destination_revisions');
        Schema::dropIfExists('x_change_commercial_partner_revisions');
        Schema::dropIfExists('x_change_commercial_partners');
    }
};
