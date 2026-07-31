<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_installation_manifests', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->unsignedSmallInteger('manifest_version');
            $table->string('package_version');
            $table->string('profile');
            $table->json('active_connection_references');
            $table->string('configuration_fingerprint', 64);
            $table->timestamp('completed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_installation_manifests');
    }
};
