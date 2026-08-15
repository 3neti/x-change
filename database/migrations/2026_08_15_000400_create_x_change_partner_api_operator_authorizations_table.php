<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_partner_api_operator_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->morphs('operator', 'xchg_partner_api_operator_idx');
            $table->string('capability')->index();
            $table->string('authorization_reference');
            $table->nullableMorphs('granted_by', 'xchg_partner_api_operator_granter_idx');
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_until')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['operator_type', 'operator_id', 'capability', 'authorization_reference'],
                'x_change_partner_api_operator_authorizations_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_partner_api_operator_authorizations');
    }
};
