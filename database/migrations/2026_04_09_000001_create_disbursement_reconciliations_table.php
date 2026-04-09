<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursement_reconciliations', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('voucher_id')->nullable()->index();
            $table->string('voucher_code')->index();

            $table->string('claim_type', 20)->nullable(); // redeem | withdraw
            $table->string('provider', 100)->nullable();

            $table->string('provider_reference')->nullable()->index();
            $table->string('provider_transaction_id')->nullable()->index();
            $table->string('transaction_uuid')->nullable()->index();

            $table->string('status', 50)->index(); // pending|processing|succeeded|failed|unknown
            $table->string('internal_status', 50)->nullable()->index(); // recorded|matched|mismatched|...

            $table->decimal('amount', 18, 2)->nullable();
            $table->string('currency', 10)->nullable();

            $table->string('bank_code', 50)->nullable();
            $table->string('account_number_masked', 50)->nullable();
            $table->string('settlement_rail', 50)->nullable();

            $table->unsignedInteger('attempt_count')->default(1);
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();

            $table->boolean('needs_review')->default(false);
            $table->string('review_reason')->nullable();
            $table->text('error_message')->nullable();

            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(
                ['voucher_code', 'provider_reference', 'claim_type'],
                'disb_rec_voucher_ref_claim_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursement_reconciliations');
    }
};
