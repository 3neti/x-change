<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_stored_value_holder_bindings', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->unsignedBigInteger('voucher_id')->unique();
            $table->string('allocation_reference', 191)->unique();
            $table->string('reservation_operation_reference', 191)->unique();
            $table->string('activation_operation_reference', 191)->unique();
            $table->string('holder_type', 191);
            $table->string('holder_id', 191);
            $table->string('holder_principal_reference', 191);
            $table->string('holder_authority_reference', 191);
            $table->char('currency', 3);
            $table->string('status', 32)->default('active')->index();
            $table->timestampTz('activated_at', 6);
            $table->timestampTz('released_at', 6)->nullable();
            $table->timestampsTz(6);

            $table->index(
                ['holder_type', 'holder_id', 'status'],
                'stored_value_holder_status_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_stored_value_holder_bindings');
    }
};
