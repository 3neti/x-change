<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('x_change_lifecycle_money_runs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->string('scenario_key', 96)->index();
            $table->char('run_reference_hash', 64)->unique();
            $table->char('run_fingerprint', 64);
            $table->nullableMorphs('issuer');
            $table->string('provider_code', 64)->index();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 32)->index();
            $table->unsignedBigInteger('voucher_id')->nullable()->index();
            $table->json('result_summary')->nullable();
            $table->string('failure_reason', 191)->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('x_change_lifecycle_money_runs');
    }
};
