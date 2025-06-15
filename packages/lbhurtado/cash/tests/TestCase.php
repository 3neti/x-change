<?php

namespace LBHurtado\Cash\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as BaseTestCase;
use LBHurtado\Cash\Tests\Models\User;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginTestUser(); // Log in a test user for all tests
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'LBHurtado\\Contact\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Load configuration files
        $this->loadConfig();
    }

    protected function getPackageProviders($app)
    {
        return [
        \LBHurtado\Cash\CashServiceProvider::class,
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

        // Optional: Set web guard as the default
        $app['config']->set('auth.defaults.guard', 'web');

        // Run the migration from the local package
        $userMigration = include __DIR__ . '/../database/migrations/test/0001_01_01_000000_create_users_table.php';
        $userMigration->up();
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
            'password' => 'password',
        ]);
        $user->save();
        $this->actingAs($user); // Simulate authentication as this user
    }

    /**
     * Load the package configuration files.
     */
    protected function loadConfig()
    {
        $this->app['config']->set(
            'model-channel',
            require __DIR__ . '/../config/cash.php'
        );
    }
}
