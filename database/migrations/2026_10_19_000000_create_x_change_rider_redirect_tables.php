<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_rider_redirects', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->unsignedBigInteger('voucher_id')->unique();
            $table->text('destination');
            $table->string('destination_host');
            $table->string('fingerprint', 64);
            $table->json('policy');
            $table->unsignedBigInteger('eligible_claim_id')->nullable();
            $table->timestamp('eligible_at')->nullable();
            $table->timestamps();
        });
        Schema::create('x_change_rider_redirect_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('redirect_id')->constrained('x_change_rider_redirects');
            $table->string('event_type');
            $table->string('event_key')->unique();
            $table->uuid('attempt_reference')->nullable()->index();
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('delivered_at')->nullable()->index();
            $table->unsignedInteger('delivery_attempts')->default(0);
            $table->string('last_error_type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_rider_redirect_events');
        Schema::dropIfExists('x_change_rider_redirects');
    }
};
