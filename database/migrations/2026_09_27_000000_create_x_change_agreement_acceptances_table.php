<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_agreement_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->string('subject_type');
            $table->string('subject_id');
            $table->string('agreement_key');
            $table->string('agreement_version');
            $table->char('agreement_sha256', 64);
            $table->timestampTz('accepted_at');
            $table->string('onboarding_reference')->nullable();
            $table->string('locale', 16)->nullable();
            $table->char('ip_address_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->char('evidence_sha256', 64);
            $table->timestampsTz();

            $table->unique(
                ['subject_type', 'subject_id', 'agreement_key', 'agreement_version', 'agreement_sha256'],
                'x_change_agreement_acceptances_current_unique',
            );
            $table->index(
                ['agreement_key', 'agreement_version', 'accepted_at'],
                'x_change_agreement_acceptances_audit_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_agreement_acceptances');
    }
};
