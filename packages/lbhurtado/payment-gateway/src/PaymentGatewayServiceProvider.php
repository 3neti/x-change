<?php

namespace LBHurtado\PaymentGateway;

use LBHurtado\PaymentGateway\Gateways\Netbank\NetbankPaymentGateway;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\Wallet\Services\SystemUserResolverService;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Number;

class PaymentGatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BankRegistry::class, fn () => new BankRegistry());

        $this->mergeConfigFrom(
            __DIR__ . '/../config/disbursement.php',
            'disbursement'
        );

        $this->mergeConfigFrom(
            __DIR__ . '/../config/payment-gateway.php',
            'payment-gateway'
        );

        $this->mergeConfigFrom(
            __DIR__ . '/../config/payment.php',
            'payment'
        );

        $this->app->singleton(SystemUserResolverService::class, fn () => new SystemUserResolverService());

        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            $concrete = config('payment-gateway.gateway', NetbankPaymentGateway::class);
            return $app->make($concrete);
        });

//        // You could default to User::class / "mobile", but allow end‐user override in config:
//        $model  = config('disbursement.reference.model', \App\Models\User::class);
//        $column = config('disbursement.reference.column', 'mobile');
//
//        $this->app->singleton(\LBHurtado\PaymentGateway\Services\ReferenceLookup::class, function($app) use ($model, $column) {
//            return new \LBHurtado\PaymentGateway\Services\ReferenceLookup($model, $column);
//        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->registerRoutes();

        // Allow publishing the configuration files
        $this->publishes([
            __DIR__ . '/../config/disbursement.php' => config_path('disbursement.php'),
            __DIR__ . '/../config/payment-gateway.php' => config_path('payment-gateway.php'),
            __DIR__ . '/../config/payment.php' => config_path('payment.php'),
        ], 'config');

        // Use PHP as the default currency globally
        Number::useCurrency('PHP');
    }


//    protected function registerRoutes(): void
//    {
//        Route::middleware('api') // Apply `api` middleware group
//        ->prefix('api')      // Optional: prefix with `/api` like Laravel default
//        ->group(__DIR__ . '/../routes/api.php');
//    }

//    protected function registerRoutes(): void
//    {
//        Route::group([
//            'prefix' => config('payment-gateway.routes.prefix'),
//            'middleware' => config('payment-gateway.routes.middleware'),
//        ], function () {
//            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
//        });
//    }

//    protected function registerRoutes(): void
//    {
//        if (!config('payment-gateway.routes.enabled', true)) {
//            return;
//        }
//
//        Route::group([
//            'prefix' => config('payment-gateway.routes.prefix'),
//            'middleware' => config('payment-gateway.routes.middleware'),
//        ], function () {
//            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
//        });
//    }

    protected function registerRoutes(): void
    {
        if (!config('payment-gateway.routes.enabled', true)) {
            return;
        }

        $version = trim(config('payment-gateway.routes.version', ''), '/');
        $prefix = trim(config('payment-gateway.routes.prefix', ''), '/');

        $fullPrefix = collect([$version, $prefix])
            ->filter()
            ->implode('/');

        Route::group([
            'prefix' => $fullPrefix,
            'middleware' => config('payment-gateway.routes.middleware', ['api']),
            'as' => config('payment-gateway.routes.name_prefix', 'pg.'),
            'domain' => config('payment-gateway.routes.domain'),
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        });
    }
}
