<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_campaign_payment_monitoring_controls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_payment_qr_binding_id');
            $table->unique('campaign_payment_qr_binding_id', 'xchg_campaign_monitor_binding_unique');
            $table->foreign('campaign_payment_qr_binding_id', 'xchg_campaign_monitor_binding_foreign')
                ->references('id')
                ->on('x_change_campaign_payment_qr_bindings')
                ->restrictOnDelete();
            $table->string('mode', 16)->default('paused')->index();
            $table->unsignedBigInteger('generation')->default(0);
            $table->string('transition_reason', 120)->nullable();
            $table->string('actor_type', 191)->nullable();
            $table->string('actor_id', 191)->nullable();
            $table->timestampTz('transitioned_at', 6)->nullable();
            $table->timestampsTz();

            $table->index(['mode', 'transitioned_at'], 'xchg_campaign_monitor_mode_transition_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_campaign_payment_monitoring_controls');
    }
};
