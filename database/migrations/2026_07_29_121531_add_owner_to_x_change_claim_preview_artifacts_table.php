<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('x_change_claim_preview_artifacts', function (Blueprint $table) {
            $table->dropUnique(['artifact_fingerprint']);
            $table->nullableMorphs('owner', 'claim_preview_owner_index');
            $table->unique(
                ['owner_type', 'owner_id', 'artifact_fingerprint'],
                'claim_preview_owner_fingerprint_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('x_change_claim_preview_artifacts', function (Blueprint $table) {
            $table->dropUnique('claim_preview_owner_fingerprint_unique');
            $table->dropMorphs('owner', 'claim_preview_owner_index');
            $table->unique('artifact_fingerprint');
        });
    }
};
