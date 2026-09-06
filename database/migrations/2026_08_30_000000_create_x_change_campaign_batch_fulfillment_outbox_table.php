<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_campaign_batch_fulfillment_outbox', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('campaign_worksheet_authorization_id')
                ->unique('campaign_batch_outbox_authorization_unique')
                ->constrained('campaign_worksheet_authorizations')
                ->restrictOnDelete();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('available_at', 6);
            $table->timestampTz('locked_at', 6)->nullable();
            $table->timestampTz('completed_at', 6)->nullable();
            $table->string('last_error_class', 190)->nullable();
            $table->timestampsTz(6);

            $table->index(['status', 'available_at', 'id'], 'campaign_batch_outbox_delivery_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_campaign_batch_fulfillment_outbox');
    }
};
