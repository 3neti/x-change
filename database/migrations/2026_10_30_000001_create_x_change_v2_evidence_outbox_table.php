<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_v2_evidence_outbox', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key')->unique();
            $table->unsignedBigInteger('voucher_id');
            $table->string('event_type');
            $table->string('evidence_reference');
            $table->json('payload');
            $table->timestampTz('occurred_at');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_v2_evidence_outbox');
    }
};
