<?php

namespace LBHurtado\ModelInput;

use Illuminate\Support\ServiceProvider;

class ModelInputServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/model-input.php',
            'model-input'
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Allow publishing the configuration files
        $this->publishes([
            __DIR__ . '/../config/model-input.php' => config_path('model-input.php'),
        ], 'config');
    }
}
