<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_account_lifecycles', function (Blueprint $table): void {
            $table->id();
            $table->string('owner_type');
            $table->string('owner_id');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['owner_type', 'owner_id'], 'x_change_account_lifecycle_owner_unique');
        });

        Schema::create('x_change_account_lifecycle_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lifecycle_id')->constrained('x_change_account_lifecycles');
            $table->string('previous_status');
            $table->string('new_status');
            $table->string('actor_type');
            $table->string('actor_id');
            $table->text('reason');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_account_lifecycle_events');
        Schema::dropIfExists('x_change_account_lifecycles');
    }
};
