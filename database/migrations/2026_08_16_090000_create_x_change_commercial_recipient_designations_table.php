<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_commercial_recipient_designations', function (Blueprint $table): void {
            $table->id();
            $table->string('designation_reference')->unique();
            $table->string('counterparty_reference')->index();
            $table->string('commercial_role')->index();
            $table->jsonb('component_scope');
            $table->string('agreement_reference')->index();
            $table->string('settlement_designation_reference');
            $table->string('tax_profile_reference')->nullable();
            $table->string('origin')->index();
            $table->string('authority_reference')->unique();
            $table->char('authority_hash', 64)->unique();
            $table->string('source_reference');
            $table->string('representative_type')->nullable();
            $table->string('representative_reference')->nullable();
            $table->char('accepted_snapshot_hash', 64);
            $table->char('acceptance_evidence_hash', 64)->nullable();
            $table->nullableMorphs('activated_by');
            $table->timestampTz('effective_from')->index();
            $table->timestampTz('effective_until')->nullable()->index();
            $table->timestampTz('activated_at')->index();
            $table->string('revocation_reference')->nullable()->unique();
            $table->timestampTz('revoked_at')->nullable()->index();
            $table->timestampsTz();

            $table->index(
                ['counterparty_reference', 'commercial_role', 'revoked_at'],
                'xchg_commercial_designation_active_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_commercial_recipient_designations');
    }
};
