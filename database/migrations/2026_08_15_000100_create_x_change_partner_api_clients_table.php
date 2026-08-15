<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_partner_api_clients', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->uuid('oauth_client_id')->unique();
            $table->string('name');
            $table->string('issuer_type');
            $table->string('issuer_id');
            $table->string('environment')->default('sandbox');
            $table->string('status')->default('draft');
            $table->json('scopes');
            $table->json('mandate');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['issuer_type', 'issuer_id']);
            $table->index(['environment', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_partner_api_clients');
    }
};
