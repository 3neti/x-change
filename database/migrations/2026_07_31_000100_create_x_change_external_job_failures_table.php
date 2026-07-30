<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_external_job_failures', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->string('job_type', 128)->index();
            $table->string('subject_type', 64)->index();
            $table->string('subject_id', 128)->index();
            $table->string('provider_code', 64)->nullable()->index();
            $table->string('trigger', 64)->nullable()->index();
            $table->string('failure_type', 128)->index();
            $table->timestampTz('failed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_external_job_failures');
    }
};
