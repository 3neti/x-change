<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_commercial_billable_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commercial_sale_id');
            $table->string('event_reference')->unique();
            $table->string('event_type')->index();
            $table->string('recognition_policy_reference')->index();
            $table->string('source_event_reference')->index();
            $table->string('component_reference')->index();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_amount_minor');
            $table->unsignedBigInteger('total_amount_minor');
            $table->string('currency', 3);
            $table->char('payload_hash', 64);
            $table->string('status')->default('received')->index();
            $table->string('reversal_reference')->nullable()->index();
            $table->timestampTz('received_at');
            $table->timestampTz('posted_at')->nullable();
            $table->timestampTz('reversed_at')->nullable();
            $table->timestampsTz();

            $table->foreign(
                'commercial_sale_id',
                'xchg_billable_event_sale_fk',
            )
                ->references('id')
                ->on('x_change_commercial_sales')
                ->restrictOnDelete();
            $table->unique(
                ['commercial_sale_id', 'component_reference'],
                'xchg_billable_event_sale_component_uq',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_commercial_billable_events');
    }
};
