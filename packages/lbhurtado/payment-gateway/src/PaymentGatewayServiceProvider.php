<?php

namespace LBHurtado\PaymentGateway;

use Illuminate\Support\Facades\Route;
use LBHurtado\PaymentGateway\Gateways\Netbank\NetbankPaymentGateway;
use LBHurtado\PaymentGateway\Services\SystemUserResolverService;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
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
            'payment-gateway'
        );

        $this->mergeConfigFrom(
            __DIR__ . '/../config/wallet.php',
            'wallet'
        );

        $this->app->singleton(SystemUserResolverService::class, fn () => new SystemUserResolverService());

        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            $concrete = config('payment-gateway.gateway', NetbankPaymentGateway::class);
            return $app->make($concrete);
        });
    }

    public function boot(): void
    {
        $this->registerRoutes();

        // Allow publishing the configuration files
        $this->publishes([
            __DIR__ . '/../config/account.php' => config_path('account.php'),
            __DIR__ . '/../config/disbursement.php' => config_path('disbursement.php'),
            __DIR__ . '/../config/payment-gateway.php' => config_path('payment-gateway.php'),

        ], 'config');

        $this->publishes([
            __DIR__ . '/../config/wallet.php' => config_path('wallet.php'),
        ], 'wallet-config');

        // Use PHP as the default currency globally
        Number::useCurrency('PHP');
    }


    protected function registerRoutes(): void
    {
        Route::middleware('api') // Apply `api` middleware group
        ->prefix('api')      // Optional: prefix with `/api` like Laravel default
        ->group(__DIR__ . '/../routes/api.php');
    }
}
