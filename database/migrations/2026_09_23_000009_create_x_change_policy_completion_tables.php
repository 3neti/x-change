<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_policy_completion_requests', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('completion_claim_evidence_projection_id')->unique()->constrained('x_change_completion_claim_evidence_projections')->restrictOnDelete();
            $table->string('driver_id');
            $table->string('driver_version');
            $table->string('idempotency_key')->unique();
            $table->char('preparation_fingerprint', 64);
            $table->json('safe_context');
            $table->text('private_payload');
            $table->string('status')->index();
            $table->morphs('requester', 'xchg_policy_completion_requester');
            $table->string('authorization_reference');
            $table->timestampTz('requested_at');
            $table->nullableMorphs('approver', 'xchg_policy_completion_approver');
            $table->string('approval_reference')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('x_change_policy_completion_outcomes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('policy_completion_request_id')->unique()->constrained('x_change_policy_completion_requests')->restrictOnDelete();
            $table->string('status')->index();
            $table->string('result_code');
            $table->string('provider_reference')->nullable();
            $table->json('safe_result');
            $table->text('private_result')->nullable();
            $table->char('outcome_hash', 64);
            $table->morphs('recorded_by', 'xchg_policy_completion_recorder');
            $table->timestampTz('recorded_at');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_policy_completion_outcomes');
        Schema::dropIfExists('x_change_policy_completion_requests');
    }
};
