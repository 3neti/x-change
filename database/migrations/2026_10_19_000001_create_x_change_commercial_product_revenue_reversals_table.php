<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('x_change_commercial_product_revenue_reversals')) {
            return;
        }

        try {
            Schema::create('x_change_commercial_product_revenue_reversals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('commercial_sale_id')->constrained('x_change_commercial_sales', indexName: 'xchg_product_reversal_sale_fk')->restrictOnDelete();
                $table->foreignId('commercial_allocation_id')->constrained('x_change_commercial_allocations', indexName: 'xchg_product_reversal_allocation_fk')->restrictOnDelete();
                $table->string('reason_reference');
                $table->unsignedBigInteger('amount_minor');
                $table->char('currency', 3);
                $table->string('reverse_operation_reference')->unique('xchg_product_reversal_operation_unique');
                $table->string('status')->index('xchg_product_reversal_status_index');
                $table->timestampTz('reversed_at');
                $table->timestampsTz();

                $table->unique(['commercial_sale_id', 'commercial_allocation_id'], 'xchg_product_reversal_sale_allocation_unique');
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '42710') {
                throw $exception;
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_commercial_product_revenue_reversals');
    }
};
