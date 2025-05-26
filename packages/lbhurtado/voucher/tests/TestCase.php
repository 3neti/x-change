<?php

namespace LBHurtado\Voucher\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'LBHurtado\\Voucher\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            \FrittenKeeZ\Vouchers\VouchersServiceProvider::class,
            \LBHurtado\Voucher\VoucherServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        config()->set('data.validation_strategy', 'always');
        config()->set('data.max_transformation_depth', 6);
        config()->set('data.throw_when_max_transformation_depth_reached', 6);
        config()->set('data.normalizers', [
            \Spatie\LaravelData\Normalizers\ModelNormalizer::class,
            // Spatie\LaravelData\Normalizers\FormRequestNormalizer::class,
            \Spatie\LaravelData\Normalizers\ArrayableNormalizer::class,
            \Spatie\LaravelData\Normalizers\ObjectNormalizer::class,
            \Spatie\LaravelData\Normalizers\ArrayNormalizer::class,
            \Spatie\LaravelData\Normalizers\JsonNormalizer::class,
        ]);
        config()->set('model-status.status_model', \Spatie\ModelStatus\Status::class);

        // Run the published voucher migration from the vendor
        $migration = include 'vendor/frittenkeez/laravel-vouchers/publishes/migrations/2018_06_12_000000_create_voucher_tables.php';
        $migration->up();

        // Run the cash migration from the local package
        $cashMigration = include __DIR__ . '/../database/migrations/2024_08_02_202500_create_cash_table.php';
        $cashMigration->up();
        $statusMigration = include __DIR__ . '/../database/migrations/2024_08_03_202500_create_statuses_table.php';
        $statusMigration->up();
        $tagMigration = include __DIR__ . '/../database/migrations/2024_08_04_202500_create_tag_tables.php';
        $tagMigration->up();
    }
}
