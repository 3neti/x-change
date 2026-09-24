<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_provisional_coverages', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->char('coverage_key', 64)->unique();
            $table->foreignId('campaign_payment_recognition_id');
            $table->unique('campaign_payment_recognition_id', 'xchg_provisional_recognition_unique');
            $table->foreign('campaign_payment_recognition_id', 'xchg_provisional_recognition_foreign')
                ->references('id')
                ->on('x_change_campaign_payment_recognitions')
                ->restrictOnDelete();
            $table->foreignId('envelope_id');
            $table->unique('envelope_id', 'xchg_provisional_envelope_unique');
            $table->foreign('envelope_id', 'xchg_provisional_envelope_foreign')
                ->references('id')
                ->on('envelopes')
                ->restrictOnDelete();
            $table->foreignId('endpoint_campaign_id')
                ->constrained('x_change_lead_campaigns')
                ->restrictOnDelete();
            $table->foreignId('campaign_payment_qr_binding_id')
                ->constrained('x_change_campaign_payment_qr_bindings')
                ->restrictOnDelete();
            $table->string('campaign_revision_id');
            $table->string('driver_id', 128);
            $table->string('driver_version', 32);
            $table->string('status', 32)->index();
            $table->string('coverage_type', 128)->index();
            $table->unsignedBigInteger('coverage_amount_minor')->nullable();
            $table->char('currency', 3);
            $table->timestampTz('effective_at')->index();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->json('payment_snapshot');
            $table->json('terms_snapshot');
            $table->json('authorization_snapshot');
            $table->char('payment_snapshot_hash', 64);
            $table->char('terms_snapshot_hash', 64);
            $table->char('authorization_snapshot_hash', 64);
            $table->char('snapshot_hash', 64);
            $table->timestampTz('bound_at')->index();
            $table->timestampsTz();

            $table->index(
                ['campaign_payment_qr_binding_id', 'effective_at'],
                'xchg_provisional_coverage_binding_effective_index',
            );
            $table->index(
                ['driver_id', 'driver_version'],
                'xchg_provisional_coverage_driver_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_provisional_coverages');
    }
};
