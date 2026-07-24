<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_commercial_sales', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->string('acceptance_event_reference')->unique();
            $table->string('source_commercial_event_reference')->index();
            $table->string('buyer_reference')->index();
            $table->string('quote_reference')->index();
            $table->string('catalog_reference');
            $table->unsignedInteger('catalog_version');
            $table->string('waterfall_policy_reference');
            $table->unsignedInteger('waterfall_policy_version');
            $table->string('attribution_reference');
            $table->unsignedInteger('attribution_version');
            $table->char('currency', 3);
            $table->unsignedBigInteger('total_price_minor');
            $table->char('snapshot_hash', 64);
            $table->json('snapshot');
            $table->string('source_client_funds_position_reference');
            $table->string('commercial_clearing_position_reference');
            $table->string('charge_operation_reference')->nullable()->unique();
            $table->string('status')->index();
            $table->timestampTz('accepted_at');
            $table->timestampTz('posted_at')->nullable();
            $table->timestampTz('reversed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('x_change_commercial_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commercial_sale_id')
                ->constrained('x_change_commercial_sales')
                ->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('policy_rule_reference');
            $table->string('line_type');
            $table->string('category');
            $table->string('recipient_reference');
            $table->string('destination_position_reference');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status')->index();
            $table->string('treasury_operation_reference')->nullable()->unique();
            $table->string('treasury_reversal_operation_reference')->nullable()->unique();
            $table->json('metadata');
            $table->timestampsTz();

            $table->unique(['commercial_sale_id', 'sequence']);
            $table->unique(['commercial_sale_id', 'policy_rule_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_commercial_allocations');
        Schema::dropIfExists('x_change_commercial_sales');
    }
};
