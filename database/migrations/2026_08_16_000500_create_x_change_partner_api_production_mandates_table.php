<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_partner_api_production_mandates', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->string('name', 120);
            $table->morphs('issuer', 'xchg_partner_api_mandate_issuer_idx');
            $table->string('status', 40)->default('awaiting_approval')->index();
            $table->json('scopes');
            $table->json('mandate');
            $table->char('snapshot_hash', 64)->index();
            $table->morphs('maker', 'xchg_partner_api_mandate_maker_idx');
            $table->nullableMorphs('checker', 'xchg_partner_api_mandate_checker_idx');
            $table->foreignId('partner_api_client_id')
                ->nullable()
                ->constrained('x_change_partner_api_clients')
                ->restrictOnDelete();
            $table->timestampTz('submitted_at');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'submitted_at'], 'xchg_partner_api_mandate_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_partner_api_production_mandates');
    }
};
