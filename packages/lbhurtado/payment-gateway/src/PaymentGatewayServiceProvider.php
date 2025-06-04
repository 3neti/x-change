<?php

namespace LBHurtado\PaymentGateway;

use LBHurtado\PaymentGateway\Services\SystemUserResolverService;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Number;

class PaymentGatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BankRegistry::class, fn () => new BankRegistry());
        $this->mergeConfigFrom(
            __DIR__ . '/../config/account.php',
            'account'
        );

        $this->mergeConfigFrom(
            __DIR__ . '/../config/disbursement.php',
            'disbursement'
        );

        $this->mergeConfigFrom(
            __DIR__ . '/../config/payment-gateway.php',
            'disbursement'
        );

        $this->app->singleton(SystemUserResolverService::class, fn () => new SystemUserResolverService());
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        // Allow publishing the configuration files
        $this->publishes([
            __DIR__ . '/../config/disbursement.php' => config_path('disbursement.php'),
            __DIR__ . '/../config/payment-gateway.php' => config_path('payment-gateway.php'),
        ], 'config');


        // Use PHP as the default currency globally
        Number::useCurrency('PHP');
    }
}
