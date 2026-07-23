<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_funding_recovery_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funding_recovery_id')
                ->constrained('x_change_funding_recoveries')
                ->restrictOnDelete();
            $table->foreignId('funding_settlement_id')
                ->constrained('x_change_funding_settlements')
                ->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->unsignedBigInteger('wallet_transaction_id')->unique();
            $table->uuid('wallet_transaction_uuid')->unique();
            $table->timestampTz('paid_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['funding_recovery_id', 'funding_settlement_id'],
                'x_change_funding_recovery_payment_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_funding_recovery_payments');
    }
};
