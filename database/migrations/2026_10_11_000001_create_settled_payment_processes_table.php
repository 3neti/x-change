<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_settled_payment_processes', function (Blueprint $table): void {
            $table->id();
            $table->string('payment_type');
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('observation_id');
            $table->string('flow');
            $table->unsignedBigInteger('gross_amount_minor');
            $table->boolean('sms_enabled_snapshot');
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('contact_source')->nullable();
            $table->string('sms_status')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['payment_type', 'payment_id']);
            $table->index('observation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_settled_payment_processes');
    }
};
