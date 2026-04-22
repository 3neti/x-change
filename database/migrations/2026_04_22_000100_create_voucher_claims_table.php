<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_claims', function (Blueprint $table) {
            $table->id();

            $table->foreignId('voucher_id')
                ->constrained('vouchers')
                ->cascadeOnDelete();

            $table->unsignedInteger('claim_number')->default(1);

            // unified claim semantics
            $table->string('claim_type')->default('claim'); // claim | redeem | withdraw (compat)
            $table->string('status')->default('pending');   // pending | succeeded | failed | pending_review

            // store money in minor units for correctness
            $table->bigInteger('requested_amount_minor')->nullable();
            $table->bigInteger('disbursed_amount_minor')->nullable();
            $table->bigInteger('remaining_balance_minor')->nullable();

            $table->string('currency', 10)->default('PHP');

            $table->string('claimer_mobile')->nullable();
            $table->string('recipient_country', 10)->nullable();

            $table->string('bank_code')->nullable();
            $table->string('account_number_masked')->nullable();

            $table->string('idempotency_key')->nullable()->index();
            $table->string('reference')->nullable()->index();

            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->text('failure_message')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['voucher_id', 'claim_number']);
            $table->index(['voucher_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_claims');
    }
};
