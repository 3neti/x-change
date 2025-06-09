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
        Schema::create('balance_access_logs', function (Blueprint $table) {
            $table->id();

            // Morph to wallet
            $table->morphs('wallet');

            // Amount and currency
            $table->string('amount'); // Store as string to preserve precision
            $table->string('currency', 3);

            // Morph to requestor (admin, system user, vendor, etc.)
            $table->morphs('requestor');

            $table->timestamp('accessed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_access_logs');
    }
};
