<?php


declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('voucher_collections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('voucher_id')->index();
            $table->unsignedInteger('collection_number')->default(1);

            $table->string('status')->index();

            $table->bigInteger('requested_amount_minor')->default(0);
            $table->bigInteger('collected_amount_minor')->default(0);
            $table->string('currency', 3)->default('PHP');

            $table->string('provider')->nullable()->index();
            $table->string('provider_reference')->nullable()->index();
            $table->string('provider_transaction_id')->nullable()->index();

            $table->string('payer_mobile')->nullable()->index();
            $table->string('payer_name')->nullable();

            $table->unsignedBigInteger('wallet_transaction_id')->nullable()->index();
            $table->string('idempotency_key')->nullable()->index();

            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->text('failure_message')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['voucher_id', 'collection_number'], 'voucher_collections_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_collections');
    }
};
