<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('x_change_public_auto_registrations', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 26)->unique();
            $table->foreignId('funding_intent_id')->unique()->constrained('x_change_funding_intents');
            $table->string('contact_type')->nullable();
            $table->string('contact_id')->nullable();
            $table->string('user_type')->nullable();
            $table->string('user_id')->nullable();
            $table->string('status', 32)->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('idempotency_key_hash', 64)->unique();
            $table->json('metadata')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
            $table->index(['contact_type', 'contact_id']);
            $table->index(['user_type', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_public_auto_registrations');
    }
};
