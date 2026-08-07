<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_commercial_offerings', function (Blueprint $table): void {
            $table->string('origin')->default('maker_checker_revision')->after('status')->index();
            $table->string('source_package')->nullable()->after('snapshot');
            $table->string('source_package_version')->nullable()->after('source_package');
            $table->string('commissioning_manifest_reference')->nullable()->after('source_package_version');
        });

        Schema::create('x_change_commercial_offering_activations', function (Blueprint $table): void {
            $table->id();
            $table->string('profile')->index();
            $table->foreignId('commercial_offering_id')
                ->constrained('x_change_commercial_offerings')
                ->restrictOnDelete();
            $table->string('offering_reference');
            $table->unsignedInteger('offering_version');
            $table->char('snapshot_hash', 64)->index();
            $table->string('origin');
            $table->string('authority');
            $table->string('activation_reference');
            $table->string('source_package')->nullable();
            $table->string('source_package_version')->nullable();
            $table->timestampTz('activated_at')->index();
            $table->timestampTz('deactivated_at')->nullable()->index();
            $table->timestampsTz();

            $table->unique(
                ['profile', 'activation_reference'],
                'xchg_comm_offering_activation_reference_unique',
            );
            $table->index(
                ['profile', 'deactivated_at', 'activated_at'],
                'xchg_comm_offering_activation_current_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_commercial_offering_activations');

        Schema::table('x_change_commercial_offerings', function (Blueprint $table): void {
            $table->dropIndex(['origin']);
            $table->dropColumn([
                'origin',
                'source_package',
                'source_package_version',
                'commissioning_manifest_reference',
            ]);
        });
    }
};
