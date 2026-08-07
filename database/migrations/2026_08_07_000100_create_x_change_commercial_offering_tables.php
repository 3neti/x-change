<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_commercial_offerings', function (Blueprint $table): void {
            $table->id();
            $table->string('reference');
            $table->unsignedInteger('version');
            $table->string('profile')->index();
            $table->string('status')->index();
            $table->char('currency', 3);
            $table->char('snapshot_hash', 64)->index();
            $table->json('snapshot');
            $table->nullableMorphs('created_by', 'xchg_comm_offerings_created_by_idx');
            $table->nullableMorphs('submitted_by', 'xchg_comm_offerings_submitted_by_idx');
            $table->nullableMorphs('approved_by', 'xchg_comm_offerings_approved_by_idx');
            $table->string('authorization_reference')->nullable();
            $table->timestampTz('effective_at');
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('retired_at')->nullable();
            $table->timestampsTz();

            $table->unique(['reference', 'version']);
        });

        Schema::create('x_change_commercial_operator_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->morphs('operator', 'xchg_comm_operator_authorizations_operator_idx');
            $table->string('capability')->index();
            $table->string('authorization_reference');
            $table->nullableMorphs('granted_by', 'xchg_comm_operator_authorizations_granter_idx');
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_until')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['operator_type', 'operator_id', 'capability', 'authorization_reference'],
                'x_change_commercial_operator_authorizations_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_commercial_operator_authorizations');
        Schema::dropIfExists('x_change_commercial_offerings');
    }
};
