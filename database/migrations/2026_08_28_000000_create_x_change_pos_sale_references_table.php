<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_pos_sale_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('voucher_id')->unique('xchg_pos_sale_voucher_unique')->constrained('vouchers')->restrictOnDelete();
            $table->string('sale_reference', 190)->unique('xchg_pos_sale_reference_unique');
            $table->string('order_reference', 190)->nullable()->index('xchg_pos_order_reference_index');
            $table->string('purpose', 255)->nullable();
            $table->morphs('operator', 'xchg_pos_sale_operator_index');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_pos_sale_references');
    }
};
