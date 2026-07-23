<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'country')) {
                $table->string('country')->nullable();
            }

            if (! Schema::hasColumn('users', 'metadata')) {
                $table->json('metadata')->nullable();
            }

            if (! Schema::hasColumn('users', 'mobile')) {
                $table->string('mobile')->nullable()->unique();
            }

            if (! Schema::hasColumn('users', 'mobile_verified_at')) {
                $table->timestamp('mobile_verified_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'mobile')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique('users_mobile_unique');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'country')) {
                $table->dropColumn('country');
            }

            foreach (['mobile_verified_at', 'mobile', 'metadata'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
