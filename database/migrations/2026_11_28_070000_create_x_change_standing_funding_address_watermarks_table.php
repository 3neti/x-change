<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_standing_funding_address_watermarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('standing_funding_address_id')
                ->constrained('x_change_standing_funding_addresses')
                ->restrictOnDelete();
            $table->char('provider_transaction_key', 64);
            $table->string('provider_transaction_id', 191);
            $table->timestampTz('baselined_at');
            $table->timestamps();

            $table->unique(
                ['standing_funding_address_id', 'provider_transaction_key'],
                'x_change_funding_watermarks_address_transaction_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_standing_funding_address_watermarks');
    }
};
