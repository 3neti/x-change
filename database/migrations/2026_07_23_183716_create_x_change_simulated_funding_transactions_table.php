<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_simulated_funding_transactions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->string('provider_request_id', 191)->unique();
            $table->string('provider_transaction_id', 191)->unique();
            $table->string('provider_event_id', 191)->unique();
            $table->string('funding_address', 191)->index();
            $table->longText('payer_mobile_ciphertext');
            $table->char('payer_mobile_hash', 64)->index();
            $table->unsignedBigInteger('gross_amount_minor');
            $table->unsignedBigInteger('fee_amount_minor')->default(0);
            $table->char('currency', 3);
            $table->string('status', 32)->index();
            $table->char('payload_hash', 64);
            $table->timestampTz('occurred_at');
            $table->timestampTz('settled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_simulated_funding_transactions');
    }
};
