<?php

namespace LBHurtado\Voucher;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Number;

class VoucherServiceProvider extends ServiceProvider
{
    /**
     * Register bindings or package services.
     */
    public function register(): void
    {

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
