<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_pay_code_templates', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->string('owner_type', 191);
            $table->string('owner_id', 191);
            $table->string('name', 80);
            $table->string('description', 240)->nullable();
            $table->string('base_template_key', 64);
            $table->longText('instructions_ciphertext');
            $table->boolean('include_amount')->default(false);
            $table->boolean('include_purpose')->default(true);
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->index(
                ['owner_type', 'owner_id', 'status'],
                'x_change_pay_code_templates_owner_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_pay_code_templates');
    }
};
