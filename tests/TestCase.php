<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Tests;

use Bavix\Wallet\WalletServiceProvider as BavixWalletServiceProvider;
use FrittenKeeZ\Vouchers\VouchersServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use LBHurtado\Cash\CashServiceProvider;
use LBHurtado\Contact\ContactServiceProvider;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\EmiCoreServiceProvider;
use LBHurtado\Instruction\Database\Seeders\InstructionItemSeeder;
use LBHurtado\Instruction\InstructionServiceProvider;
use LBHurtado\ModelChannel\ModelChannelServiceProvider;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\VoucherServiceProvider;
use LBHurtado\Wallet\WalletServiceProvider as LBHurtadoWalletServiceProvider;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Providers\XChangeServiceProvider;
use LBHurtado\XChange\Tests\Fakes\FakeAuditLogger;
use LBHurtado\XChange\Tests\Fakes\FakePayoutProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelData\Normalizers\ArrayableNormalizer;
use Spatie\LaravelData\Normalizers\ArrayNormalizer;
use Spatie\LaravelData\Normalizers\JsonNormalizer;
use Spatie\LaravelData\Normalizers\ModelNormalizer;
use Spatie\LaravelData\Normalizers\ObjectNormalizer;
use Spatie\SchemalessAttributes\SchemalessAttributesServiceProvider;

abstract class TestCase extends Orchestra
{
    protected FakePayoutProvider $fakePayoutProvider;

    protected FakeAuditLogger $fakeAuditLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakePayoutProvider = new FakePayoutProvider;
        $this->app->instance(PayoutProvider::class, $this->fakePayoutProvider);

        $this->fakeAuditLogger = new FakeAuditLogger;
        $this->app->instance(AuditLoggerContract::class, $this->fakeAuditLogger);

        $this->seedInstructionItems();
    }

    public function fakePayoutProvider(): FakePayoutProvider
    {
        return $this->fakePayoutProvider;
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            SchemalessAttributesServiceProvider::class,
            BavixWalletServiceProvider::class,
            CashServiceProvider::class,
            LBHurtadoWalletServiceProvider::class,
            ModelChannelServiceProvider::class,
            VouchersServiceProvider::class,
            ContactServiceProvider::class,
            VoucherServiceProvider::class,
            InstructionServiceProvider::class,
            EmiCoreServiceProvider::class,
            XChangeServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $app['config']->set('app.locale', 'en');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('auth.defaults.guard', 'web');

        // Force voucher package to use the extended voucher model.
        $app['config']->set('vouchers.models.voucher', Voucher::class);

        // Minimal route required by GenerateVouchers metadata creation.
        Route::get('/redeem', fn () => 'ok')->name('redeem.start');

        $app['config']->set('x-change.routes.web', true);
        $app['config']->set('x-change.routes.api', true);

        $app['config']->set('x-change.product.name', 'X-Change');
        $app['config']->set('x-change.product.code', 'x-change');
        $app['config']->set('x-change.product.default_currency', 'PHP');
        $app['config']->set('x-change.product.default_country', 'PH');

        $app['config']->set('x-change.terminology.voucher', 'Pay Code');
        $app['config']->set('x-change.terminology.voucher_code', 'Pay Code');
        $app['config']->set('x-change.terminology.redeem', 'Claim');
        $app['config']->set('x-change.terminology.withdraw', 'Withdraw');
        $app['config']->set('x-change.terminology.wallet', 'Wallet');
        $app['config']->set('x-change.terminology.account', 'Account');

        $app['config']->set('x-change.pricing.currency', 'PHP');
        $app['config']->set('x-change.pricing.base_fee', 0.0);
        $app['config']->set('x-change.pricing.components', [
            'cash' => 0.0,
            'kyc' => 25.0,
            'otp' => 2.0,
            'selfie' => 5.0,
            'signature' => 3.0,
            'location' => 1.0,
            'webhook' => 0.0,
            'email_feedback' => 0.0,
            'sms_feedback' => 0.0,
        ]);

        $app['config']->set('data.validation_strategy', 'always');
        $app['config']->set('data.max_transformation_depth', 6);
        $app['config']->set('data.throw_when_max_transformation_depth_reached', 6);
        $app['config']->set('data.normalizers', [
            ModelNormalizer::class,
            ArrayableNormalizer::class,
            ObjectNormalizer::class,
            ArrayNormalizer::class,
            JsonNormalizer::class,
        ]);
        $app['config']->set('data.date_format', 'Y-m-d\\TH:i:sP');

        config()->set('x-change.onboarding.issuer_model', \LBHurtado\XChange\Tests\Fakes\User::class);

        $app['config']->set('model-channel.rules.mobile', ['string']);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Laravel default tables, including users. Loaded once only.
        $this->loadLaravelMigrations();

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // Lower-level dependencies first.
        $this->runBaseWalletTablesMigrations();

        // Base vouchers table from 3neti/laravel-vouchers.
        $this->runBaseVoucherTablesMigration();

        // Cash package migrations.
        $this->loadCashPackageMigrations();

        // Model Channel package migrations.
        $this->loadModelChannelPackageMigrations();

        // Contact package migrations.
        $this->loadContactPackageMigrations();

        // Model-input package migrations.
        $this->loadModelInputPackageMigrations();

        // Higher-level voucher package migrations.
        $this->loadVoucherPackageMigrations();

        // Instruction package migrations.
        $this->loadInstructionPackageMigrations();

        // Extra voucher-support tables used by voucher package tests/flows.
        $this->runVoucherSupportMigrations();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function loadInstructionPackageMigrations(): void
    {
        $path = $this->packageRoot(InstructionServiceProvider::class).'/database/migrations';

        if (is_dir($path) && (glob($path.'/*.php') ?: []) !== []) {
            $this->loadMigrationsFrom($path);
        }
    }

    protected function runBaseWalletTablesMigrations(): void
    {
        if (Schema::hasTable('wallets')) {
            return;
        }

        $providers = [
            BavixWalletServiceProvider::class,
            LBHurtadoWalletServiceProvider::class,
        ];

        foreach ($providers as $providerClass) {
            $this->runMigrationFilesFromCandidates($this->packageMigrationPaths($providerClass));

            if (Schema::hasTable('wallets')) {
                return;
            }
        }

        throw new \RuntimeException('Wallet bootstrap failed: wallets table was not created.');
    }

    protected function runBaseVoucherTablesMigration(): void
    {
        if (Schema::hasTable('vouchers')) {
            return;
        }

        $packageRoot = $this->packageRoot(VouchersServiceProvider::class);

        $candidateFiles = [
            $packageRoot.'/publishes/migrations/2018_06_12_000000_create_voucher_tables.php',
            $packageRoot.'/database/migrations/2018_06_12_000000_create_voucher_tables.php',
        ];

        foreach ($candidateFiles as $file) {
            if (! is_file($file)) {
                continue;
            }

            $this->runMigrationFile($file);

            if (Schema::hasTable('vouchers')) {
                return;
            }
        }
    }

    protected function loadCashPackageMigrations(): void
    {
        $this->runMigrationFilesFromCandidates([
            $this->packageRoot(CashServiceProvider::class).'/database/migrations',
        ]);
    }

    protected function loadModelChannelPackageMigrations(): void
    {
        $this->runMigrationFilesFromCandidates([
            $this->packageRoot(ModelChannelServiceProvider::class).'/database/migrations',
        ]);
    }

    protected function loadVoucherPackageMigrations(): void
    {
        $this->runMigrationFilesFromCandidates([
            $this->packageRoot(VouchersServiceProvider::class).'/database/migrations',
        ]);
    }

    protected function runVoucherSupportMigrations(): void
    {
        $voucherRoot = $this->packageRoot(VoucherServiceProvider::class);
        $testMigrations = $voucherRoot.'/database/test-migrations';

        if (! is_dir($testMigrations)) {
            return;
        }

        $candidateFiles = [
            $testMigrations.'/2024_07_02_202500_create_money_issuers_table.php',
            $testMigrations.'/2024_08_03_202500_create_statuses_table.php',
            $testMigrations.'/2024_08_04_202500_create_tag_tables.php',
        ];

        foreach ($candidateFiles as $file) {
            if (! is_file($file)) {
                continue;
            }

            $this->runMigrationFile($file);
        }
    }

    /**
     * @param  array<int,string>  $paths
     */
    protected function runMigrationFilesFromCandidates(array $paths): void
    {
        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $files = glob($path.'/*.php') ?: [];
            sort($files);

            if ($files === []) {
                continue;
            }

            foreach ($files as $file) {
                if ($this->shouldSkipMigrationFile($file)) {
                    continue;
                }

                $this->runMigrationFile($file);
            }

            return;
        }
    }

    protected function runMigrationFile(string $file): void
    {
        if ($this->shouldSkipMigrationFile($file)) {
            return;
        }

        $instance = include $file;

        if (is_object($instance) && method_exists($instance, 'up')) {
            $instance->up();
        }
    }

    protected function shouldSkipMigrationFile(string $file): bool
    {
        $basename = basename($file);

        $skipPatterns = [
            'create_users_table',
            'create_password_reset_tokens_table',
            'create_password_resets_table',
            'create_sessions_table',
            'create_jobs_table',
            'create_cache_table',
        ];

        foreach ($skipPatterns as $pattern) {
            if (str_contains($basename, $pattern)) {
                return true;
            }
        }

        if ($basename === '2018_06_12_000000_create_voucher_tables.php' && Schema::hasTable('vouchers')) {
            return true;
        }

        return false;
    }

    /**
     * @return array<int,string>
     */
    protected function packageMigrationPaths(string $providerClass): array
    {
        $root = $this->packageRoot($providerClass);

        return [
            $root.'/database/migrations',
            $root.'/database',
            $root.'/publishes/migrations',
        ];
    }

    protected function packageRoot(string $providerClass): string
    {
        return dirname((new ReflectionClass($providerClass))->getFileName(), 2);
    }

    public function fakeAuditLogger(): FakeAuditLogger
    {
        return $this->fakeAuditLogger;
    }

    protected function seedInstructionItems(): void
    {
        $this->seed(InstructionItemSeeder::class);
    }

    protected function runMigrationDirectory(string $path): void
    {
        if (! is_dir($path)) {
            throw new \RuntimeException("Migration directory not found: {$path}");
        }

        $files = glob($path.'/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $migration = include $file;

            if (is_object($migration) && method_exists($migration, 'up')) {
                $migration->up();
            }
        }
    }

    protected function loadContactPackageMigrations(): void
    {
        $this->runMigrationFilesFromCandidates([
            $this->packageRoot(ContactServiceProvider::class).'/database/migrations',
        ]);
    }

    protected function loadModelInputPackageMigrations(): void
    {
        $candidatePaths = [
            base_path('vendor/3neti/laravel-model-input/database/migrations'),
            dirname(__DIR__, 4).'/vendor/3neti/laravel-model-input/database/migrations',
        ];

        $this->runMigrationFilesFromCandidates($candidatePaths);
    }
}
