<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_commercial_tax_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('reference');
            $table->unsignedInteger('version');
            $table->char('jurisdiction', 2)->index();
            $table->char('currency', 3)->index();
            $table->string('tax_type')->index();
            $table->string('calculation_basis');
            $table->unsignedInteger('rate_basis_points');
            $table->string('rounding_method');
            $table->string('rounding_scope');
            $table->string('collection_method');
            $table->string('tax_recipient_reference');
            $table->timestampTz('effective_from')->index();
            $table->timestampTz('effective_until')->nullable()->index();
            $table->char('snapshot_hash', 64)->unique();
            $table->jsonb('snapshot');
            $table->timestampsTz();

            $table->unique(['reference', 'version'], 'xchg_tax_profile_reference_version_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_commercial_tax_profiles');
    }
};
