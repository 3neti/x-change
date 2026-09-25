<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_campaign_workflow_publications', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('endpoint_campaign_id');
            $table->string('campaign_revision_id', 128);
            $table->foreignId('draft_template_id');
            $table->unique('draft_template_id', 'xchg_workflow_draft_unique');
            $table->unique(['endpoint_campaign_id', 'campaign_revision_id'], 'xchg_workflow_campaign_revision_unique');
            $table->foreign('endpoint_campaign_id', 'xchg_workflow_campaign_foreign')->references('id')->on('x_change_lead_campaigns')->restrictOnDelete();
            $table->foreign('draft_template_id', 'xchg_workflow_draft_foreign')->references('id')->on('x_change_pay_code_templates')->restrictOnDelete();
            $table->longText('snapshot');
            $table->char('snapshot_hash', 64);
            $table->string('published_by_type');
            $table->string('published_by_id');
            $table->timestampTz('published_at');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_campaign_workflow_publications');
    }
};
