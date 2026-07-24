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
        Schema::create('x_change_standing_funding_qr_artifacts', function (Blueprint $table) {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('standing_funding_address_id')
                ->constrained('x_change_standing_funding_addresses')
                ->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('version')->default(1);
            $table->char('artifact_fingerprint', 64)->unique();
            $table->char('merchant_profile_fingerprint', 64)->nullable()->index();
            $table->string('mime_type', 64);
            $table->string('qr_mode', 32);
            $table->string('transaction_type', 32);
            $table->boolean('embedded_amount')->default(false);
            $table->boolean('provider_generated')->default(true);
            $table->longText('payload_ciphertext');
            $table->text('display_snapshot_ciphertext');
            $table->timestamp('generated_at');
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->index(
                ['standing_funding_address_id', 'status'],
                'standing_funding_qr_address_status_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('x_change_standing_funding_qr_artifacts');
    }
};
