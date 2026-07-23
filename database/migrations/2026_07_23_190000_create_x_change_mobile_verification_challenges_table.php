<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_mobile_verification_challenges', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->string('user_type');
            $table->string('user_id');
            $table->string('mobile_hash', 64);
            $table->string('provider');
            $table->string('status');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['user_type', 'user_id', 'status'], 'x_change_mobile_verification_user_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_mobile_verification_challenges');
    }
};
