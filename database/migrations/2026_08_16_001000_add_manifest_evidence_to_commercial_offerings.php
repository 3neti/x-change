<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_commercial_offerings', function (Blueprint $table): void {
            $table->string('manifest_schema')->nullable()->after('snapshot');
            $table->char('manifest_hash', 64)->nullable()->after('manifest_schema')->index();
            $table->longText('manifest_yaml')->nullable()->after('manifest_hash');
        });
    }

    public function down(): void
    {
        Schema::table('x_change_commercial_offerings', function (Blueprint $table): void {
            $table->dropIndex(['manifest_hash']);
            $table->dropColumn([
                'manifest_schema',
                'manifest_hash',
                'manifest_yaml',
            ]);
        });
    }
};
