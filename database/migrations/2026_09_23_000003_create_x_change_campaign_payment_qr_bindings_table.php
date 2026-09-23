<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_campaign_payment_qr_bindings', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('endpoint_campaign_id')
                ->constrained('x_change_lead_campaigns')
                ->restrictOnDelete();
            $table->string('campaign_revision_id', 80);
            $table->foreignId('standing_funding_address_id')
                ->constrained('x_change_standing_funding_addresses')
                ->restrictOnDelete();
            $table->foreignId('standing_funding_qr_artifact_id')
                ->constrained('x_change_standing_funding_qr_artifacts')
                ->restrictOnDelete();
            $table->string('entry_mode', 40);
            $table->string('provider_code', 64);
            $table->char('currency', 3);
            $table->string('amount_mode', 16);
            $table->unsignedBigInteger('fixed_amount_minor')->nullable();
            $table->timestampTz('available_from')->nullable();
            $table->timestampTz('available_until')->nullable();
            $table->json('permitted_payment_rules');
            $table->char('configuration_hash', 64)->unique();
            $table->timestampsTz();

            $table->unique(
                ['endpoint_campaign_id', 'campaign_revision_id'],
                'xchg_campaign_payment_qr_revision_unique',
            );
            $table->unique(
                'standing_funding_address_id',
                'xchg_campaign_payment_qr_address_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_campaign_payment_qr_bindings');
    }
};
