<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Exceptions\InsufficientWalletBalance;
use LBHurtado\XChange\Exceptions\PayCodeIssuerNotResolved;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;
use LBHurtado\XChange\Exceptions\PayCodeWalletNotResolved;
use LBHurtado\XChange\Services\ApiResponseFactory;

class XChangeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            $this->packagePath('config/x-change.php'),
            'x-change'
        );

        $this->registerServices();
        $this->registerIntegrations();
        $this->registerServiceContracts();
        $this->registerIntegrationContracts();
    }

    public function boot(): void
    {
        $this->bootConfig();
        $this->bootRoutes();
        $this->bootExceptionRendering();
    }

    protected function registerServices(): void
    {
        $services = (array) config('x-change.services', []);

        foreach ($services as $key => $concrete) {
            if (! is_string($key) || ! is_string($concrete) || $concrete === '') {
                continue;
            }

            $this->app->singleton("x-change.services.{$key}", function ($app) use ($concrete) {
                return $app->make($concrete);
            });
        }
    }

    protected function registerIntegrations(): void
    {
        $integrations = (array) config('x-change.integrations', []);

        foreach ($integrations as $key => $concrete) {
            if (! is_string($key) || ! is_string($concrete) || $concrete === '') {
                continue;
            }

            $this->app->singleton("x-change.integrations.{$key}", function ($app) use ($concrete) {
                return $app->make($concrete);
            });
        }
    }

    protected function registerServiceContracts(): void
    {
        $contracts = (array) config('x-change.service_contracts', []);

        foreach ($contracts as $contract => $serviceKey) {
            if (! is_string($contract) || ! is_string($serviceKey) || $serviceKey === '') {
                continue;
            }

            $this->app->singleton($contract, function ($app) use ($serviceKey) {
                return $app->make("x-change.services.{$serviceKey}");
            });
        }
    }

    protected function registerIntegrationContracts(): void
    {
        $contracts = (array) config('x-change.integration_contracts', []);

        foreach ($contracts as $contract => $integrationKey) {
            if (! is_string($contract) || ! is_string($integrationKey) || $integrationKey === '') {
                continue;
            }

            $this->app->singleton($contract, function ($app) use ($integrationKey) {
                return $app->make("x-change.integrations.{$integrationKey}");
            });
        }
    }

    protected function bootConfig(): void
    {
        $this->publishes([
            $this->packagePath('config/x-change.php') => config_path('x-change.php'),
        ], 'x-change-config');
    }

    protected function bootRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $config = $this->app['config'];

        if ((bool) $config->get('x-change.routes.web', true)) {
            $this->loadRoutesFrom($this->packagePath('routes/web.php'));
        }

        if ((bool) $config->get('x-change.routes.api', true)) {
            $this->loadRoutesFrom($this->packagePath('routes/api.php'));
        }
    }

    protected function bootExceptionRendering(): void
    {
        $exceptions = $this->app->make('Illuminate\Contracts\Debug\ExceptionHandler');

        if (! method_exists($exceptions, 'renderable')) {
            return;
        }

        $exceptions->renderable(function (ValidationException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->error(
                'The given data was invalid.',
                'VALIDATION_ERROR',
                $e->errors(),
                422,
            );
        });

        $exceptions->renderable(function (InsufficientWalletBalance $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->errorFromThrowable(
                $e,
                'INSUFFICIENT_WALLET_BALANCE',
                [],
                422,
            );
        });

        $exceptions->renderable(function (PayCodeIssuerNotResolved $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->errorFromThrowable(
                $e,
                'PAY_CODE_ISSUER_NOT_RESOLVED',
                [],
                401,
            );
        });

        $exceptions->renderable(function (PayCodeWalletNotResolved $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->errorFromThrowable(
                $e,
                'PAY_CODE_WALLET_NOT_RESOLVED',
                [],
                422,
            );
        });

        $exceptions->renderable(function (PayCodeIssuanceFailed $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->errorFromThrowable(
                $e,
                'PAY_CODE_ISSUANCE_FAILED',
                [],
                500,
            );
        });
    }

    protected function apiResponses(): ApiResponseFactory
    {
        return $this->app->make(ApiResponseFactory::class);
    }

    protected function packagePath(string $path = ''): string
    {
        $base = dirname(__DIR__, 2);

        return $path !== ''
            ? $base.DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR)
            : $base;
    }
}
