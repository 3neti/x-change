<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_commercial_principals', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->string('legal_name');
            $table->string('authorization_reference');
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_commercial_principals');
    }
};
