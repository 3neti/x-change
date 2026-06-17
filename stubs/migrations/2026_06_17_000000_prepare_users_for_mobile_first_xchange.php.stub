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
            if (! Schema::hasColumn('users', 'mobile')) {
                $table->string('mobile')->nullable()->unique()->after('id');
            }

            if (! Schema::hasColumn('users', 'mobile_verified_at')) {
                $table->timestamp('mobile_verified_at')->nullable()->after('mobile');
            }

            if (! Schema::hasColumn('users', 'identity_level')) {
                $table->string('identity_level')->nullable()->after('email_verified_at');
            }

            if (! Schema::hasColumn('users', 'onboarding_meta')) {
                $table->json('onboarding_meta')->nullable()->after('identity_level');
            }
        });

        $this->makeEmailNullable();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['onboarding_meta', 'identity_level', 'mobile_verified_at', 'mobile'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function makeEmailNullable(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'email')) {
                $table->string('email')->nullable()->change();
            }
        });
    }
};
