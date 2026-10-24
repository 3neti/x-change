<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_enrollment_offers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->string('owner_type', 191);
            $table->string('owner_id', 191);
            $table->string('title', 120);
            $table->text('consent_statement');
            $table->string('consent_version', 40);
            $table->boolean('active')->default(true);
            $table->timestampsTz(6);
            $table->index(['owner_type', 'owner_id']);
        });

        Schema::create('x_change_enrollment_consents', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('enrollment_offer_id')->constrained('x_change_enrollment_offers')->restrictOnDelete();
            $table->text('name_ciphertext');
            $table->text('mobile_ciphertext');
            $table->char('mobile_hash', 64);
            $table->string('consent_version', 40);
            $table->text('consent_statement');
            $table->char('statement_hash', 64);
            $table->timestampTz('consented_at', 6);
            $table->timestampsTz(6);
            $table->unique(['enrollment_offer_id', 'consent_version', 'mobile_hash'], 'enrollment_consent_once_per_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_enrollment_consents');
        Schema::dropIfExists('x_change_enrollment_offers');
    }
};
