<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_stored_value_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id', 191)->unique();
            $table->foreignId('stored_value_holder_binding_id')->constrained('x_change_stored_value_holder_bindings')->restrictOnDelete();
            $table->foreignId('stored_value_collection_id')->nullable()->constrained('x_change_stored_value_collections')->restrictOnDelete();
            $table->string('event_type', 64);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('balance_after_minor');
            $table->char('currency', 3);
            $table->timestampTz('occurred_at', 6);
            $table->string('journal_status', 16)->default('pending');
            $table->unsignedInteger('journal_attempts')->default(0);
            $table->timestampTz('journaled_at', 6)->nullable();
            $table->string('journal_error', 255)->nullable();
            $table->timestampsTz(6);
            $table->index(['journal_status', 'occurred_at', 'id'], 'sv_audit_delivery_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_stored_value_audit_events');
    }
};
