<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_commercial_component_economics_manifests', function (Blueprint $table): void {
            $table->id();
            $table->string('reference');
            $table->unsignedInteger('version');
            $table->string('profile')->index();
            $table->string('origin')->default('installation_baseline')->index();
            $table->string('authority');
            $table->foreignId('commercial_offering_id')
                ->constrained('x_change_commercial_offerings')
                ->restrictOnDelete();
            $table->string('offering_reference');
            $table->unsignedInteger('offering_version');
            $table->char('offering_snapshot_hash', 64)->index();
            $table->char('offering_manifest_hash', 64)->index();
            $table->string('currency', 3);
            $table->char('snapshot_hash', 64)->index();
            $table->jsonb('snapshot');
            $table->string('artifact_schema');
            $table->char('artifact_hash', 64)->unique();
            $table->longText('artifact_yaml');
            $table->string('source_package')->nullable();
            $table->string('source_package_version')->nullable();
            $table->string('commissioning_manifest_reference')->nullable();
            $table->timestampTz('effective_at')->index();
            $table->timestampsTz();

            $table->unique(
                ['reference', 'version'],
                'xchg_comm_component_economics_version_unique',
            );
            $table->index(
                ['profile', 'effective_at'],
                'xchg_comm_component_economics_lookup_idx',
            );
        });

        Schema::create('x_change_commercial_component_economics_activations', function (Blueprint $table): void {
            $table->id();
            $table->string('profile')->index();
            $table->foreignId('commercial_component_economics_id')
                ->constrained('x_change_commercial_component_economics_manifests')
                ->restrictOnDelete();
            $table->foreignId('previous_activation_id')
                ->nullable()
                ->constrained('x_change_commercial_component_economics_activations')
                ->restrictOnDelete();
            $table->string('authority');
            $table->string('activation_reference');
            $table->string('authorization_reference')->nullable();
            $table->nullableMorphs('actor');
            $table->string('source_package')->nullable();
            $table->string('source_package_version')->nullable();
            $table->timestampTz('activated_at')->index();
            $table->timestampsTz();

            $table->unique(
                ['profile', 'activation_reference'],
                'xchg_comm_component_economics_activation_ref_unique',
            );
            $table->index(
                ['profile', 'activated_at'],
                'xchg_comm_component_economics_current_idx',
            );
        });

        Schema::create('x_change_commercial_component_economics_heads', function (Blueprint $table): void {
            $table->string('profile')->primary();
            $table->foreignId('current_activation_id')->nullable();
            $table->unique(
                'current_activation_id',
                'xchg_comm_economics_head_activation_unique',
            );
            $table->foreign(
                'current_activation_id',
                'xchg_comm_economics_head_activation_fk',
            )
                ->references('id')
                ->on('x_change_commercial_component_economics_activations')
                ->restrictOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('x_change_commercial_component_economics_heads');
        Schema::dropIfExists('x_change_commercial_component_economics_activations');
        Schema::dropIfExists('x_change_commercial_component_economics_manifests');
        Schema::enableForeignKeyConstraints();
    }
};
