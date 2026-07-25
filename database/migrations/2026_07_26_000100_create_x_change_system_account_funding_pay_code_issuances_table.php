<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_system_account_funding_pay_code_issuances', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->char('idempotency_reference_hash', 64)->unique();
            $table->char('request_fingerprint', 64);
            $table->string('source', 64)->index();
            $table->string('issuer_type', 191);
            $table->string('issuer_id', 191)->index();
            $table->string('recipient_type', 191)->nullable();
            $table->string('recipient_id', 191)->nullable()->index();
            $table->boolean('bearer')->default(false);
            $table->string('connection_reference', 191)->index();
            $table->string('provider_code', 64)->index();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('evidence_reference', 191)->nullable()->index();
            $table->unsignedBigInteger('voucher_id')->nullable()->unique();
            $table->string('reservation_operation_reference', 191)
                ->nullable()
                ->unique();
            $table->string('status', 32)->index();
            $table->timestampTz('expires_at');
            $table->timestampTz('issued_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'x_change_system_account_funding_pay_code_issuances',
        );
    }
};
