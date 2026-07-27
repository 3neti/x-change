<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_funding_transfer_amount_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funding_request_id')
                ->unique()
                ->constrained('x_change_funding_requests')
                ->restrictOnDelete();
            $table->string('provider_code', 64);
            $table->string('connection_reference', 191);
            $table->char('currency', 3);
            $table->unsignedBigInteger('requested_amount_minor');
            $table->unsignedBigInteger('matching_adjustment_minor');
            $table->unsignedBigInteger('expected_amount_minor');
            $table->string('status', 32)->index();
            $table->char('active_key', 64)->nullable()->unique();
            $table->timestampTz('reserved_at');
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('reusable_after')->index();
            $table->timestampTz('matched_at')->nullable();
            $table->timestampTz('credited_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(
                ['provider_code', 'connection_reference', 'currency', 'status'],
                'x_change_funding_transfer_amount_reservation_scope_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_funding_transfer_amount_reservations');
    }
};
