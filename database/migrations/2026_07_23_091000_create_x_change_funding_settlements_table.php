<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_funding_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funding_intent_id')
                ->unique()
                ->constrained('x_change_funding_intents')
                ->restrictOnDelete();
            $table->foreignId('provider_funding_observation_id')
                ->unique()
                ->constrained('provider_funding_observations')
                ->restrictOnDelete();
            $table->string('provider_code', 64)->index();
            $table->string('account_reference', 191)->index();
            $table->unsignedBigInteger('gross_amount_minor');
            $table->unsignedBigInteger('fee_amount_minor')->default(0);
            $table->unsignedBigInteger('net_amount_minor');
            $table->char('currency', 3);
            $table->string('treasury_inventory_reference', 191)->index();
            $table->string('treasury_operation_reference', 191)->unique();
            $table->unsignedBigInteger('wallet_transaction_id')->unique();
            $table->uuid('wallet_transaction_uuid')->unique();
            $table->timestampTz('settled_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_funding_settlements');
    }
};
