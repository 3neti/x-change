<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_payment_attempts', function (Blueprint $table): void {
            $table->timestampTz('last_monitored_at')->nullable()->index();
        });

        Schema::create('x_change_observed_payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_attempt_id')->constrained('x_change_payment_attempts')->restrictOnDelete();
            $table->string('provider_code', 64);
            $table->char('provider_transaction_hash', 64);
            $table->longText('provider_transaction_id_ciphertext');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('provider_status', 64);
            $table->string('settlement_rail', 64)->nullable();
            $table->longText('payer_name_ciphertext')->nullable();
            $table->longText('payer_account_ciphertext')->nullable();
            $table->longText('payer_institution_ciphertext')->nullable();
            $table->longText('payer_mobile_ciphertext')->nullable();
            $table->timestampTz('occurred_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->timestampTz('observed_at');
            $table->timestamps();

            $table->unique(['provider_code', 'provider_transaction_hash'], 'x_change_observed_payment_provider_transaction_unique');
            $table->index(['payment_attempt_id', 'occurred_at'], 'x_change_observed_payment_attempt_occurred_index');
        });

        Schema::create('x_change_observed_payment_statuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('observed_payment_transaction_id')
                ->constrained('x_change_observed_payment_transactions')->restrictOnDelete();
            $table->string('provider_status', 64);
            $table->timestampTz('observed_at');
            $table->timestamps();

            $table->index(
                ['observed_payment_transaction_id', 'id'],
                'x_change_observed_payment_status_history_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_observed_payment_statuses');
        Schema::dropIfExists('x_change_observed_payment_transactions');
        Schema::table('x_change_payment_attempts', function (Blueprint $table): void {
            $table->dropIndex(['last_monitored_at']);
            $table->dropColumn('last_monitored_at');
        });
    }
};
