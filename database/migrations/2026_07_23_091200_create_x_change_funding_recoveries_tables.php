<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_funding_recoveries', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('funding_intent_id')
                ->unique()
                ->constrained('x_change_funding_intents')
                ->restrictOnDelete();
            $table->foreignId('funding_settlement_id')
                ->unique()
                ->constrained('x_change_funding_settlements')
                ->restrictOnDelete();
            $table->foreignId('reversal_observation_id')
                ->unique()
                ->constrained('provider_funding_observations')
                ->restrictOnDelete();
            $table->string('account_reference', 191)->index();
            $table->unsignedBigInteger('reversal_amount_minor');
            $table->unsignedBigInteger('recovered_amount_minor');
            $table->unsignedBigInteger('outstanding_amount_minor');
            $table->char('currency', 3);
            $table->string('treasury_reversal_operation_reference', 191)->unique();
            $table->unsignedBigInteger('wallet_transaction_id')->nullable()->unique();
            $table->uuid('wallet_transaction_uuid')->nullable()->unique();
            $table->string('status', 32)->index();
            $table->timestampTz('opened_at');
            $table->timestampTz('recovered_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('x_change_funding_account_holds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funding_recovery_id')
                ->unique()
                ->constrained('x_change_funding_recoveries')
                ->restrictOnDelete();
            $table->string('account_reference', 191)->index();
            $table->unsignedBigInteger('outstanding_amount_minor');
            $table->char('currency', 3);
            $table->string('status', 32)->default('active')->index();
            $table->timestampTz('placed_at');
            $table->timestampTz('released_at')->nullable();
            $table->string('released_by_type', 191)->nullable();
            $table->string('released_by_id', 191)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(
                ['account_reference', 'status'],
                'x_change_funding_account_holds_active_lookup',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_funding_account_holds');
        Schema::dropIfExists('x_change_funding_recoveries');
    }
};
