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
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        Number::useCurrency('PHP');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
//        Factory::guessFactoryNamesUsing(
//            fn (string $modelName) => 'LBHurtado\\Voucher\\Database\\Factories\\'.class_basename($modelName).'Factory'
//        );
    }
}
