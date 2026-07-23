<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('x_change_standing_funding_addresses', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->char('binding_key', 64)->unique();
            $table->nullableMorphs('owner');
            $table->string('account_reference', 191)->index();
            $table->string('provider_code', 64)->index();
            $table->string('purpose', 32)->index();
            $table->string('recognition_mode', 32)->index();
            $table->string('status', 32)->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('provider_reference', 191);
            $table->text('funding_address_ciphertext');
            $table->char('funding_address_hash', 64)->unique();
            $table->text('destination_snapshot_ciphertext')->nullable();
            $table->char('destination_fingerprint', 64)->nullable()->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('minimum_amount_minor')->nullable();
            $table->unsignedBigInteger('maximum_amount_minor')->nullable();
            $table->unsignedBigInteger('daily_limit_minor')->nullable();
            $table->timestampTz('activated_at');
            $table->timestampTz('last_qr_issued_at')->nullable();
            $table->timestampTz('last_checked_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->timestampTz('retired_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('x_change_standing_funding_addresses');
    }
};
