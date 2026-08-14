<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_rider_library_entries', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->string('owner_type', 191);
            $table->string('owner_id', 191);
            $table->string('kind', 16);
            $table->string('format', 16)->nullable();
            $table->longText('content_ciphertext');
            $table->longText('label_ciphertext')->nullable();
            $table->char('content_fingerprint', 64);
            $table->timestampTz('saved_at')->nullable();
            $table->timestampTz('pinned_at')->nullable();
            $table->unsignedInteger('use_count')->default(0);
            $table->timestampTz('first_used_at')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['owner_type', 'owner_id', 'kind', 'content_fingerprint'],
                'x_change_rider_library_owner_kind_fingerprint_unique',
            );
            $table->index(
                ['owner_type', 'owner_id', 'kind', 'last_used_at'],
                'x_change_rider_library_owner_kind_used_index',
            );
            $table->index(
                ['owner_type', 'owner_id', 'saved_at', 'pinned_at'],
                'x_change_rider_library_owner_saved_pinned_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_rider_library_entries');
    }
};
