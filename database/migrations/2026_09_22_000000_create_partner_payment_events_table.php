<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_partner_payment_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->unsignedBigInteger('collection_id')->unique();
            $table->unsignedBigInteger('partner_api_client_id');
            $table->string('partner_reference');
            $table->text('body');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('available_at');
            $table->uuid('lease_token')->nullable();
            $table->timestampTz('lease_expires_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->string('last_error')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_partner_payment_events');
    }
};
