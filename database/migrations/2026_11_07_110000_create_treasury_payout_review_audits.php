<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_treasury_payout_review_audits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('outbox_id')->index();
            $table->unsignedBigInteger('reconciliation_id')->nullable();
            $table->string('actor_type');
            $table->string('actor_id');
            $table->string('outcome', 24);
            $table->text('reason_ciphertext');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_treasury_payout_review_audits');
    }
};
