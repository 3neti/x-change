<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_voucher_slice_execution_outbox', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('execution_id')->constrained('x_change_voucher_slice_executions')->restrictOnDelete();
            $table->string('event_type', 80);
            $table->char('event_fingerprint', 64)->unique();
            $table->string('status', 24)->default('pending');
            $table->json('payload');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('occurred_at', 6);
            $table->timestampTz('delivered_at', 6)->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz(6);

            $table->index(['status', 'occurred_at', 'id'], 'slice_exec_outbox_delivery_idx');
            $table->index(['execution_id', 'event_type'], 'slice_exec_outbox_execution_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_voucher_slice_execution_outbox');
    }
};
