<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_external_job_failures', function (Blueprint $table): void {
            $table->json('metadata')->nullable()->after('failure_type');
        });
    }

    public function down(): void
    {
        Schema::table('x_change_external_job_failures', function (Blueprint $table): void {
            $table->dropColumn('metadata');
        });
    }
};
