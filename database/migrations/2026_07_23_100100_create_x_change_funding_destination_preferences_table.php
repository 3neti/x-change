<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_funding_destination_preferences', function (Blueprint $table): void {
            $table->id();
            $table->morphs('owner');
            $table->string('provider_code', 64);
            $table->string('mode', 32)->default('shared');
            $table->foreignId('provider_account_link_id')
                ->nullable()
                ->constrained('xchange_provider_account_links')
                ->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('changed_by_type', 191)->nullable();
            $table->string('changed_by_id', 191)->nullable();
            $table->timestamps();

            $table->unique(
                ['owner_type', 'owner_id', 'provider_code'],
                'x_change_funding_destination_owner_provider_unique',
            );
            $table->index(['provider_code', 'mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_funding_destination_preferences');
    }
};
