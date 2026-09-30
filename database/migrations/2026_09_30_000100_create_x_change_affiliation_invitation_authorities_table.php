<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_affiliation_invitation_authorities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('voucher_id')->unique();
            $table->unsignedBigInteger('provisioning_offer_id')->unique();
            $table->text('encrypted_claim_token');
            $table->string('snapshot_hash', 64);
            $table->timestamps();

            $table->foreign('voucher_id')->references('id')->on('vouchers')->cascadeOnDelete();
            $table->foreign('provisioning_offer_id')->references('id')->on('x_provisioning_offers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_affiliation_invitation_authorities');
    }
};
