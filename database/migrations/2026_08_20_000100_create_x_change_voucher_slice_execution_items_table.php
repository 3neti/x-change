<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_voucher_slice_execution_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('execution_id')->constrained('x_change_voucher_slice_executions')->restrictOnDelete();
            $table->foreignId('voucher_id')->constrained('vouchers')->restrictOnDelete();
            $table->string('slice_id', 80);
            $table->string('label', 120);
            $table->unsignedInteger('sequence');
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 32)->default('reserved');
            $table->timestampTz('reserved_at', 6);
            $table->timestampTz('consumed_at', 6)->nullable();
            $table->timestampsTz(6);

            $table->unique(['voucher_id', 'slice_id'], 'slice_exec_item_voucher_slice_uq');
            $table->unique(['execution_id', 'slice_id'], 'slice_exec_item_execution_slice_uq');
            $table->index(['voucher_id', 'status'], 'slice_exec_item_voucher_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_voucher_slice_execution_items');
    }
};
