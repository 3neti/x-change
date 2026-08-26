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
        Schema::create('x_change_partner_api_pay_code_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_api_client_id')
                ->constrained('x_change_partner_api_clients')
                ->restrictOnDelete();
            $table->string('external_reference', 190);
            $table->foreignId('voucher_id')
                ->constrained((new Voucher)->getTable())
                ->restrictOnDelete();
            $table->char('terms_hash', 64);
            $table->timestampsTz();

            $table->unique(
                ['partner_api_client_id', 'external_reference'],
                'partner_api_pay_code_reference_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_partner_api_pay_code_references');
    }
};
