<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LBHurtado\Voucher\Models\Voucher;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_partner_issuance_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_api_client_id')->constrained('x_change_partner_api_clients')->restrictOnDelete();
            $table->string('external_reference', 190);
            $table->char('idempotency_key_hash', 64);
            $table->char('request_hash', 64);
            $table->char('instructions_hash', 64);
            $table->unsignedBigInteger('principal_minor');
            $table->string('currency', 3);
            $table->string('status', 24)->default('pending');
            $table->string('released_by_type')->nullable();
            $table->string('released_by_id')->nullable();
            $table->string('release_evidence_reference')->nullable();
            $table->text('release_reason')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->foreignId('funding_order_id')->nullable()->constrained('x_change_pay_code_issuance_funding_orders')->restrictOnDelete();
            $table->foreignId('voucher_id')->nullable()->constrained((new Voucher)->getTable())->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['partner_api_client_id', 'external_reference'], 'partner_issuance_reservation_reference_unique');
            $table->unique(['partner_api_client_id', 'idempotency_key_hash'], 'partner_issuance_reservation_key_unique');
            $table->unique('funding_order_id', 'partner_issuance_reservation_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_partner_issuance_reservations');
    }
};
