<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_checkouts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->string('source', 64);
            $table->string('owner_type', 191);
            $table->string('owner_id', 191);
            $table->foreignId('funding_order_id')->nullable()->unique()
                ->constrained('x_change_pay_code_issuance_funding_orders')->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->restrictOnDelete();
            $table->string('status', 32)->default('draft');
            $table->string('selected_method', 32)->default('qr_ph');
            $table->char('guest_token_hash', 64)->nullable();
            $table->char('session_hash', 64)->nullable();
            $table->timestampTz('draft_expires_at')->nullable();
            $table->longText('instructions_ciphertext')->nullable();
            $table->longText('pricing_snapshot_ciphertext')->nullable();
            $table->longText('visitor_mobile_ciphertext')->nullable();
            $table->char('visitor_mobile_hash', 64)->nullable();
            $table->string('contact_source', 64)->nullable();
            $table->timestampTz('placed_at')->nullable();
            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id', 'created_at'], 'x_change_checkout_owner_created_idx');
            $table->index(['status', 'updated_at'], 'x_change_checkout_status_updated_idx');
        });

        Schema::create('x_change_checkout_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checkout_id')->constrained('x_change_checkouts')->restrictOnDelete();
            $table->string('event_type', 64);
            $table->string('actor_type', 191);
            $table->string('actor_id', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('occurred_at');

            $table->index(['checkout_id', 'occurred_at'], 'x_change_checkout_events_time_idx');
        });

        Schema::create('x_change_checkout_refund_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checkout_id')->unique()->constrained('x_change_checkouts')->restrictOnDelete();
            $table->foreignId('funding_settlement_id')->constrained('x_change_funding_settlements')->restrictOnDelete();
            $table->string('status', 32)->default('open');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->text('reason');
            $table->longText('external_reference_ciphertext')->nullable();
            $table->string('treasury_reconciliation_reference', 191)->nullable();
            $table->string('opened_by', 191);
            $table->string('disposed_by', 191)->nullable();
            $table->timestampTz('disposed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('x_change_checkout_viewer_links', function (Blueprint $table): void {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->string('owner_type', 191);
            $table->string('owner_id', 191);
            $table->string('label', 191);
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('x_change_checkout_console_access_events', function (Blueprint $table): void {
            $table->id();
            $table->string('owner_type', 191);
            $table->string('owner_id', 191);
            $table->string('event_type', 64);
            $table->char('session_hash', 64);
            $table->char('ip_hash', 64);
            $table->char('user_agent_hash', 64);
            $table->timestampTz('occurred_at');

            $table->index(['owner_type', 'owner_id', 'occurred_at'], 'x_change_checkout_access_owner_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_checkout_console_access_events');
        Schema::dropIfExists('x_change_checkout_viewer_links');
        Schema::dropIfExists('x_change_checkout_refund_cases');
        Schema::dropIfExists('x_change_checkout_events');
        Schema::dropIfExists('x_change_checkouts');
    }
};
