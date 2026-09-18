<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_lead_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->string('owner_type');
            $table->string('owner_id');
            $table->foreignId('pay_code_template_id')
                ->constrained('x_change_pay_code_templates')
                ->restrictOnDelete();
            $table->string('active_template_version_id', 80)->nullable();
            $table->string('merchant_display_name', 120);
            $table->string('merchant_slug', 120);
            $table->string('endpoint_slug', 120);
            $table->string('title', 120);
            $table->string('description', 240)->nullable();
            $table->string('status', 32)->default('active');
            $table->unsignedBigInteger('usage_count')->default(0);
            $table->timestamp('last_started_at')->nullable();
            $table->unsignedInteger('starts_limit')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('merchant_registry_id', 120)->nullable();
            $table->string('merchant_certification_status', 32)->default('none');
            $table->json('merchant_certification_snapshot')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id', 'status'], 'x_change_lead_campaigns_owner_status_index');
            $table->index(['merchant_slug', 'status'], 'x_change_lead_campaigns_merchant_status_index');
            $table->unique(['merchant_slug', 'endpoint_slug'], 'x_change_lead_campaigns_public_endpoint_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_lead_campaigns');
    }
};
