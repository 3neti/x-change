<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('x_change_account_funding_receipts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('standing_funding_address_id')
                ->constrained('x_change_standing_funding_addresses')
                ->restrictOnDelete();
            $table->foreignId('provider_funding_observation_id')
                ->constrained('provider_funding_observations')
                ->restrictOnDelete();
            $table->char('provider_transaction_key', 64)->unique();
            $table->string('provider_code', 64)->index();
            $table->string('account_reference', 191)->index();
            $table->string('purpose', 32)->index();
            $table->string('recognition_mode_snapshot', 32);
            $table->string('status', 32)->index();
            $table->unsignedBigInteger('gross_amount_minor');
            $table->unsignedBigInteger('fee_amount_minor')->default(0);
            $table->unsignedBigInteger('net_amount_minor');
            $table->char('currency', 3);
            $table->string('suspense_reason', 64)->nullable()->index();
            $table->string('treasury_inventory_reference', 191)->nullable()->index();
            $table->string('treasury_operation_reference', 191)->nullable()->unique();
            $table->unsignedBigInteger('wallet_transaction_id')->nullable()->unique();
            $table->uuid('wallet_transaction_uuid')->nullable()->unique();
            $table->timestampTz('observed_at');
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->timestampTz('suspense_at')->nullable();
            $table->timestampTz('reversed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('x_change_account_funding_receipts');
    }
};
