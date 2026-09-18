<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('x_change_lead_campaigns', 'active_template_version_id')) {
            return;
        }

        Schema::table('x_change_lead_campaigns', function (Blueprint $table): void {
            $table->string('active_template_version_id', 80)
                ->nullable()
                ->after('pay_code_template_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('x_change_lead_campaigns', 'active_template_version_id')) {
            return;
        }

        Schema::table('x_change_lead_campaigns', function (Blueprint $table): void {
            $table->dropColumn('active_template_version_id');
        });
    }
};
