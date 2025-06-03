<?php

namespace LBHurtado\MoneyIssuer\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as BaseTestCase;
use LBHurtado\MoneyIssuer\Tests\Models\User;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginTestUser(); // Log in a test user for all tests
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'LBHurtado\\MoneyIssuer\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
        // Set the base path for the package
        if (!defined('TESTING_PACKAGE_PATH')) {
            define('TESTING_PACKAGE_PATH', __DIR__ . '/../resources/documents');
        }
    }

    protected function getPackageProviders($app)
    {
        return [
        \LBHurtado\MoneyIssuer\MoneyIssuerServiceProvider::class,
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

        // Optional: Set web guard as the default
        $app['config']->set('auth.defaults.guard', 'web');

        // Run the cash migration from the local package
        $userMigration = include __DIR__ . '/../database/migrations/0001_01_01_000000_create_users_table.php';
        $userMigration->up();
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
}
