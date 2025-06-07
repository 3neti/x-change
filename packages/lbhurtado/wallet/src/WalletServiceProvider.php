<?php

namespace LBHurtado\Wallet;

use Illuminate\Support\ServiceProvider;

class WalletServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/wallet.php',
            'wallet'
        );
    }

    public function boot(): void
    {
        // Allow publishing the configuration files
        $this->publishes([
            __DIR__ . '/../config/wallet.php' => config_path('wallet.php'),
        ], 'config');
    }
}
