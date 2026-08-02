<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_mobile_verification_challenges', function (Blueprint $table): void {
            $table->string('purpose')->after('provider');
            $table->string('provider_challenge_reference')->nullable()->after('purpose');
            $table->timestamp('provider_verified_at')->nullable()->after('verified_at');

            $table->unique(
                ['provider', 'provider_challenge_reference'],
                'x_change_mobile_verification_provider_challenge',
            );
        });
    }

    public function down(): void
    {
        Schema::table('x_change_mobile_verification_challenges', function (Blueprint $table): void {
            $table->dropUnique('x_change_mobile_verification_provider_challenge');
            $table->dropColumn([
                'purpose',
                'provider_challenge_reference',
                'provider_verified_at',
            ]);
        });
    }
};
