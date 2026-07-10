<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('x_change_cockpit_operator_issuance_activities')) {
            return;
        }

        Schema::create('x_change_cockpit_operator_issuance_activities', function (Blueprint $table): void {
            $table->id();
            $table->string('activity_id');
            $table->string('schema')->default('x-change.cockpit.operator-issuance-activity-record.v1');
            $table->string('actor_id')->nullable();
            $table->string('actor_label')->nullable();
            $table->string('source')->default('cockpit.quick-generate');
            $table->string('subject_type')->default('pay_code');
            $table->string('subject_reference')->nullable();
            $table->string('status')->default('recorded');
            $table->string('severity')->default('info');
            $table->timestampTz('occurred_at')->nullable();
            $table->string('idempotency_key_hash')->nullable();
            $table->string('correlation_id')->nullable();
            $table->string('causation_id')->nullable();
            $table->text('summary')->nullable();
            $table->json('safe_context')->nullable();
            $table->json('redaction_flags')->nullable();
            $table->string('journal_handoff_status')->default('not_wired');
            $table->string('action_handoff_status')->default('not_wired');
            $table->string('feedback_handoff_status')->default('not_wired');
            $table->timestampTz('retention_until')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->unique('activity_id', 'index_activity_id_unique');
            $table->index(['actor_id', 'occurred_at'], 'index_operator_occurred_at');
            $table->index('subject_reference', 'index_subject_reference');
            $table->index('correlation_id', 'index_correlation_id');
            $table->index('retention_until', 'index_retention_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_cockpit_operator_issuance_activities');
    }
};
