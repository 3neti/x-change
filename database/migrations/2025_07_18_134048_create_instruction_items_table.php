<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('instruction_items', function (Blueprint $table) {
            $table->id();

            // Dot-indexed path into VoucherInstructionsData (e.g. voucher.cash.validation.mobile)
            $table->string('index')->unique(); // ✅ Essential for resolving presence

            // Human-readable name (e.g. "Mobile", "Secret", "Webhook")
            $table->string('name')->index(); // 👍 Yes, useful for UI labels and logs

            // Logical grouping (cash, feedback, inputs, etc.)
            $table->string('type'); // ✅ Great for bundles and filtering

            // Price in cents (₱100.00 = 10000)
            $table->unsignedInteger('price'); // ✅ Wallet-compatible

            // Currency (e.g. PHP, USD)
            $table->string('currency'); // ✅ Good practice

            // Optional extras (like description, icon, rule, etc.)
            $table->json('meta'); // ✅ Flexible

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instruction_items');
    }
};
