<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_issuer_invoice_drafts', function (Blueprint $table): void {
            $table->uuid('reference')->primary();
            $table->string('owner_type');
            $table->string('owner_id');
            $table->string('disk');
            $table->string('path');
            $table->string('filename');
            $table->string('sha256', 64);
            $table->string('order_key')->nullable();
            $table->unsignedBigInteger('voucher_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_issuer_invoice_drafts');
    }
};
