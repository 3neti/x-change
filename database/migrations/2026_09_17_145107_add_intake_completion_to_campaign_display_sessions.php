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
        Schema::table('x_change_campaign_display_sessions', function (Blueprint $table) {
            $table->timestamp('intake_completed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('x_change_campaign_display_sessions', function (Blueprint $table) {
            $table->dropColumn('intake_completed_at');
        });
    }
};
