<?php

namespace LBHurtado\Voucher\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use LBHurtado\Voucher\Tests\Models\User;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginTestUser(); // Log in a test user for all tests
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'LBHurtado\\Voucher\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
        // Set the base path for the package
        if (!defined('TESTING_PACKAGE_PATH')) {
            define('TESTING_PACKAGE_PATH', __DIR__ . '/../resources/documents');
        }
        $this->loadEnvironment();
        $this->loadConfig();
//        Relation::morphMap([
//            'User'  => User::class,
//        ]);
    }

    protected function getPackageProviders($app)
    {
        return [
//            AuthServiceProvider::class,
            \FrittenKeeZ\Vouchers\VouchersServiceProvider::class,
            \LBHurtado\Voucher\VoucherServiceProvider::class,
            \LBHurtado\Wallet\WalletServiceProvider::class,
            \Bavix\Wallet\WalletServiceProvider::class,
            \LBHurtado\PaymentGateway\PaymentGatewayServiceProvider::class,
            \LBHurtado\Contact\ContactServiceProvider::class,
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
        config()->set('vouchers.models.voucher', \LBHurtado\Voucher\Models\Voucher::class);

        config()->set('instructions.feedback.mobile', '09171234567');
        config()->set('instructions.feedback.email', 'example@example.com');
        config()->set('instructions.feedback.webhook', 'http://example.com/webhook');

//        // Configure the web guard for authentication
//        $app['config']->set('auth.guards.web', [
//            'driver' => 'session', // Use the session driver for web guard
//            'provider' => 'users', // Reference the users provider below
//        ]);
//
//        // Add an authentication provider using the array driver
//        $app['config']->set('auth.providers.users', [
//            'driver' => 'eloquent', // Use the lightweight array driver
//            'model' => \Illuminate\Foundation\Auth\User::class, // Optionally specify Laravel's default User model
//        ]);

        // Optional: Set web guard as the default
        $app['config']->set('auth.defaults.guard', 'web');


        // Run the published voucher migration from the vendor
        $baseVoucherMigration = include 'vendor/frittenkeez/laravel-vouchers/publishes/migrations/2018_06_12_000000_create_voucher_tables.php';
        $baseVoucherMigration->up();

        // Run the cash migration from the local package
        $userMigration = include __DIR__ . '/../database/migrations/test/0001_01_01_000000_create_users_table.php';
        $userMigration->up();
        $moneyIssuerMigration = include __DIR__ . '/../database/migrations/test/2024_07_02_202500_create_money_issuers_table.php';
        $moneyIssuerMigration->up();
        $channelsMigration = include __DIR__ . '/../database/migrations/test/2024_08_02_000000_create_channels_table.php';
        $channelsMigration->up();
        $statusMigration = include __DIR__ . '/../database/migrations/test/2024_08_03_202500_create_statuses_table.php';
        $statusMigration->up();
        $tagMigration = include __DIR__ . '/../database/migrations/test/2024_08_04_202500_create_tag_tables.php';
        $tagMigration->up();
    }

    // Define a reusable method for logging in a user
    protected function loginTestUser()
    {
        $user = new User([
            'id' => 1, // Unique ID for the user
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->actingAs($user); // Simulate authentication as this user
    }

    protected function loadConfig()
    {
        $this->app['config']->set(
            'disbursement',
            require __DIR__ . '/../config/disbursement.php'
        );

        $this->app['config']->set(
            'payment-gateway',
            require __DIR__ . '/../config/payment-gateway.php'
        );
    }

    protected function loadEnvironment()
    {
        $path =__DIR__ . '/../.env';

        if (file_exists($path)) {
            \Dotenv\Dotenv::createImmutable(dirname($path), '.env')->load();
        }
    }
}
