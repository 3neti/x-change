<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_payout_locality_revisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('disbursement_reconciliation_id')->index();
            $table->string('actor_type');
            $table->string('actor_id');
            $table->string('role', 16);
            $table->text('reason');
            $table->text('previous_snapshot');
            $table->text('corrected_snapshot');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_payout_locality_revisions');
    }
};
