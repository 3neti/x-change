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
        Schema::create('x_change_claim_preview_artifacts', function (Blueprint $table) {
            $table->id();
            $table->ulid('reference')->unique();
            $table->char('artifact_fingerprint', 64)->unique();
            $table->string('scenario_key', 120)->index();
            $table->unsignedInteger('scenario_version')->default(1);
            $table->string('profile', 32)->default('issuer')->index();
            $table->string('status', 24)->default('ready')->index();
            $table->string('artifact_disk', 64)->default('local');
            $table->string('artifact_path');
            $table->json('metadata')->nullable();
            $table->timestamp('generated_at');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(
                ['scenario_key', 'profile', 'status'],
                'claim_preview_scenario_profile_status_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('x_change_claim_preview_artifacts');
    }
};
