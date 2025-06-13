<?php

namespace LBHurtado\Voucher;

use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use LBHurtado\MoneyIssuer\Services\MoneyIssuerManager;

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
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        Number::useCurrency('PHP');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->publishes([
            __DIR__ . '/../config/instructions.php' => config_path('instructions.php'),

        ], 'config');
//        Factory::guessFactoryNamesUsing(
//            fn (string $modelName) => 'LBHurtado\\Voucher\\Database\\Factories\\'.class_basename($modelName).'Factory'
//        );
    }
}
