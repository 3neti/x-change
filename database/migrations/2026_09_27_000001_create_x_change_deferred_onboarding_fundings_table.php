<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_deferred_onboarding_fundings', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->unsignedBigInteger('voucher_id')->unique();
            $table->string('subject_type');
            $table->string('subject_id');
            $table->string('required_agreement_key');
            $table->string('required_agreement_version');
            $table->char('required_agreement_sha256', 64);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('connection_reference');
            $table->string('reservation_operation_reference');
            $table->string('status');
            $table->unsignedBigInteger('agreement_acceptance_id')->nullable();
            $table->unsignedBigInteger('voucher_claim_id')->nullable();
            $table->string('treasury_operation_reference')->nullable();
            $table->timestampTz('deferred_at');
            $table->timestampTz('released_at')->nullable();
            $table->timestampsTz();

            $table->index(
                ['subject_type', 'subject_id', 'status'],
                'x_change_deferred_onboarding_subject_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_deferred_onboarding_fundings');
    }
};
