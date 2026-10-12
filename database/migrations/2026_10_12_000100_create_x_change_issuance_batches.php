<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_issuance_batches', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->string('source', 64);
            $table->string('status', 32)->index();
            $table->string('currency', 3);
            $table->unsignedBigInteger('item_count')->default(0);
            $table->unsignedBigInteger('valid_item_count')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->foreignId('funding_order_id')->nullable()->unique()
                ->constrained('x_change_pay_code_issuance_funding_orders')->restrictOnDelete();
            $table->string('uploader_mobile_ciphertext', 512)->nullable();
            $table->char('uploader_mobile_hash', 64)->nullable()->index();
            $table->char('normalized_fingerprint', 64);
            $table->json('metadata')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('funded_at')->nullable();
            $table->timestampTz('issued_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('x_change_issuance_batch_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('x_change_issuance_batches')->cascadeOnDelete();
            $table->unsignedInteger('ordinal');
            $table->unsignedInteger('source_row');
            $table->string('status', 32)->default('pending')->index();
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->char('row_fingerprint', 64);
            $table->string('pay_code', 191)->nullable();
            $table->string('claim_url', 2048)->nullable();
            $table->text('failure_message')->nullable();
            $table->longText('payload')->nullable();
            $table->timestampTz('issued_at')->nullable();
            $table->timestampsTz();

            $table->unique(['batch_id', 'ordinal']);
            $table->unique(['batch_id', 'row_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_issuance_batch_items');
        Schema::dropIfExists('x_change_issuance_batches');
    }
};
