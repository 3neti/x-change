<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_commercial_recognition_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('reference');
            $table->unsignedInteger('version');
            $table->string('trigger')->index();
            $table->string('timing')->index();
            $table->char('snapshot_hash', 64)->unique();
            $table->jsonb('snapshot');
            $table->timestampsTz();

            $table->unique(
                ['reference', 'version'],
                'xchg_recognition_policy_reference_version_uq',
            );
        });

        Schema::table('x_change_commercial_billable_events', function (Blueprint $table): void {
            $table->foreignId('commercial_recognition_policy_id')->nullable();
            $table->unsignedInteger('recognition_policy_version')->nullable();
            $table->char('recognition_policy_hash', 64)->nullable()->index();
            $table->jsonb('recognition_policy_snapshot')->nullable();

            $table->foreign(
                'commercial_recognition_policy_id',
                'xchg_billable_event_recognition_policy_fk',
            )
                ->references('id')
                ->on('x_change_commercial_recognition_policies')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('x_change_commercial_billable_events', function (Blueprint $table): void {
            $table->dropForeign(DB::getDriverName() === 'sqlite'
                ? ['commercial_recognition_policy_id']
                : 'xchg_billable_event_recognition_policy_fk');
            $table->dropIndex(['recognition_policy_hash']);
            $table->dropColumn([
                'commercial_recognition_policy_id',
                'recognition_policy_version',
                'recognition_policy_hash',
                'recognition_policy_snapshot',
            ]);
        });

        Schema::dropIfExists('x_change_commercial_recognition_policies');
    }
};
