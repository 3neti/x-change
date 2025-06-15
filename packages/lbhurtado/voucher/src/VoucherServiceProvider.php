<?php

namespace LBHurtado\Voucher;

use LBHurtado\Voucher\Providers\EventServiceProvider;
use LBHurtado\MoneyIssuer\Services\MoneyIssuerManager;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Number;

class VoucherServiceProvider extends ServiceProvider
{
    /**
     * Register bindings or package services.
     */
    public function register(): void
    {
        $this->app->singleton(MoneyIssuerManager::class, fn () => new MoneyIssuerManager(app()));

        $this->mergeConfigFrom(
            __DIR__ . '/../config/instructions.php',
            'instructions'
        );

        $this->mergeConfigFrom(
            __DIR__ . '/../config/voucher-pipeline.php',
            'voucher-pipeline'
        );
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->app->register(EventServiceProvider::class);

        Number::useCurrency('PHP');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->publishes([
            __DIR__ . '/../config/instructions.php' => config_path('instructions.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../config/voucher-pipeline.php' => config_path('voucher-pipeline.php'),
        ], 'config');
    }
}
