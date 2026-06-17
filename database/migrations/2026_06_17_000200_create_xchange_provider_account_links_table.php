<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xchange_provider_account_links', function (Blueprint $table): void {
            $table->id();
            $table->morphs('owner');

            $table->string('provider');
            $table->string('topology');
            $table->string('purpose')->nullable();
            $table->string('mode')->nullable();

            $table->foreignId('emi_provider_account_id')->nullable();
            $table->foreignId('emi_wallet_id')->nullable();
            $table->foreignId('emi_bank_account_id')->nullable();

            $table->string('provider_account_id')->nullable();
            $table->string('provider_wallet_id')->nullable();
            $table->string('provider_bank_account_id')->nullable();
            $table->string('external_uid')->nullable();

            $table->string('status')->default('pending');
            $table->string('verification_status')->nullable();
            $table->string('identity_level')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('ready_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'topology']);
            $table->index(['provider', 'provider_account_id']);
            $table->index(['provider', 'provider_wallet_id']);
            $table->index(['provider', 'external_uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xchange_provider_account_links');
    }
};
