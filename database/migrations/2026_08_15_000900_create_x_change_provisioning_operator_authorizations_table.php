<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_provisioning_operator_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->morphs('operator');
            $table->string('capability', 100);
            $table->string('authorization_reference')->unique();
            $table->nullableMorphs('granted_by');
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_until')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestamps();

            $table->index(
                ['capability', 'valid_from', 'valid_until'],
                'x_change_provisioning_operator_capability_validity',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_provisioning_operator_authorizations');
    }
};
