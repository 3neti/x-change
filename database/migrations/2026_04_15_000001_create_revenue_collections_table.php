<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_collections', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('instruction_item_id');
            $table->unsignedBigInteger('collected_by_user_id')->nullable();

            $table->string('destination_type');
            $table->unsignedBigInteger('destination_id');

            $table->bigInteger('amount');
            $table->uuid('transfer_uuid');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('instruction_item_id');
            $table->index('collected_by_user_id');
            $table->index(['destination_type', 'destination_id']);
            $table->index('transfer_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_collections');
    }
};
