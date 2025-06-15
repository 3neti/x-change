<?php

namespace LBHurtado\Cash;

use Illuminate\Support\ServiceProvider;

class CashServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/cash.php',
            'cash'
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/cash.php' => config_path('cash.php'),
        ], 'config');
    }
}
