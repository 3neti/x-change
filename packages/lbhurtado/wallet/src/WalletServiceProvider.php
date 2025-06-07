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
        $this->mergeConfigFrom(
            __DIR__ . '/../config/account.php',
            'account'
        );
    }

    public function boot(): void
    {
        // Allow publishing the configuration files
        $this->publishes([
            __DIR__ . '/../config/account.php' => config_path('account.php'),
            __DIR__ . '/../config/wallet.php' => config_path('wallet.php'),
        ], 'config');
    }
}
