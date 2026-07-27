<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_funding_request_transfer_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funding_request_id')
                ->unique()
                ->constrained('x_change_funding_requests')
                ->restrictOnDelete();
            $table->foreignId('provider_funding_observation_id')
                ->unique()
                ->constrained('provider_funding_observations')
                ->restrictOnDelete();
            $table->string('provider_code', 64)->index();
            $table->string('connection_reference', 191)->index();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 32)->index();
            $table->timestampTz('matched_at');
            $table->timestampTz('credited_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_funding_request_transfer_matches');
    }
};
