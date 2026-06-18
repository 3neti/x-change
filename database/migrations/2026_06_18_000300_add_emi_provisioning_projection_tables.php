<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('provider_accounts')) {
            Schema::create('provider_accounts', function (Blueprint $table): void {
                $table->id();
                $table->string('provider_code');
                $table->string('name');
                $table->string('merchant_id')->nullable();
                $table->text('integration_key')->nullable();
                $table->string('base_url')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('config')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('wallets')) {
            Schema::table('wallets', function (Blueprint $table): void {
                if (! Schema::hasColumn('wallets', 'provider_account_id')) {
                    $table->unsignedBigInteger('provider_account_id')->nullable()->index();
                }

                if (! Schema::hasColumn('wallets', 'provider_code')) {
                    $table->string('provider_code')->nullable()->index();
                }

                if (! Schema::hasColumn('wallets', 'provider_wallet_id')) {
                    $table->string('provider_wallet_id')->nullable()->unique();
                }

                if (! Schema::hasColumn('wallets', 'provider_account_id_value')) {
                    $table->string('provider_account_id_value')->nullable()->index();
                }

                if (! Schema::hasColumn('wallets', 'account_no')) {
                    $table->string('account_no')->nullable();
                }

                if (! Schema::hasColumn('wallets', 'external_uid')) {
                    $table->string('external_uid')->nullable()->index();
                }

                if (! Schema::hasColumn('wallets', 'wallet_type')) {
                    $table->string('wallet_type')->nullable();
                }

                if (! Schema::hasColumn('wallets', 'status')) {
                    $table->string('status')->default('active');
                }

                if (! Schema::hasColumn('wallets', 'compliance_level')) {
                    $table->string('compliance_level')->default('0');
                }

                if (! Schema::hasColumn('wallets', 'verification_status')) {
                    $table->string('verification_status')->default('PENDING');
                }

                if (! Schema::hasColumn('wallets', 'balance_cached')) {
                    $table->decimal('balance_cached', 14, 2)->default(0);
                }

                if (! Schema::hasColumn('wallets', 'currency')) {
                    $table->string('currency', 3)->default('PHP');
                }

                if (! Schema::hasColumn('wallets', 'notification_url')) {
                    $table->string('notification_url')->nullable();
                }

                if (! Schema::hasColumn('wallets', 'capture_link')) {
                    $table->text('capture_link')->nullable();
                }
            });
        }

        if (! Schema::hasTable('bank_accounts')) {
            Schema::create('bank_accounts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('wallet_id')->nullable()->index();
                $table->string('provider_bank_account_id')->nullable()->index();
                $table->string('bank_code')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('account_name')->nullable();
                $table->string('account_number_masked')->nullable();
                $table->string('status')->default('active');
                $table->boolean('is_registered')->default(true);
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bank_accounts')) {
            Schema::drop('bank_accounts');
        }

        if (Schema::hasTable('provider_accounts')) {
            Schema::drop('provider_accounts');
        }
    }
};
