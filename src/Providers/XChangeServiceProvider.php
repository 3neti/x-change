<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Console\Commands\ReconcilePendingDisbursementsCommand;
use LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract;
use LBHurtado\XChange\Contracts\DisbursementReconciliationContract;
use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Contracts\DisbursementStatusFetcherContract;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;
use LBHurtado\XChange\Contracts\RedemptionCompletionContextContract;
use LBHurtado\XChange\Contracts\RedemptionCompletionStoreContract;
use LBHurtado\XChange\Contracts\RedemptionContextResolverContract;
use LBHurtado\XChange\Contracts\RedemptionExecutionContract;
use LBHurtado\XChange\Contracts\RedemptionFlowPreparationContract;
use LBHurtado\XChange\Contracts\RedemptionProcessorContract;
use LBHurtado\XChange\Contracts\RedemptionValidationContract;
use LBHurtado\XChange\Contracts\WithdrawalExecutionContract;
use LBHurtado\XChange\Contracts\WithdrawalProcessorContract;
use LBHurtado\XChange\Contracts\WithdrawalValidationContract;
use LBHurtado\XChange\Exceptions\IdempotencyConflict;
use LBHurtado\XChange\Exceptions\InsufficientWalletBalance;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;
use LBHurtado\XChange\Exceptions\PayCodeIssuerNotResolved;
use LBHurtado\XChange\Exceptions\PayCodeWalletNotResolved;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\DefaultClaimExecutionFactory;
use LBHurtado\XChange\Services\DefaultDisbursementReconciliationService;
use LBHurtado\XChange\Services\DefaultDisbursementReconciliationStore;
use LBHurtado\XChange\Services\DefaultDisbursementStatusFetcherService;
use LBHurtado\XChange\Services\DefaultDisbursementStatusResolverService;
use LBHurtado\XChange\Services\DefaultRedemptionCompletionContextService;
use LBHurtado\XChange\Services\DefaultRedemptionContextResolverService;
use LBHurtado\XChange\Services\DefaultRedemptionExecutionService;
use LBHurtado\XChange\Services\DefaultRedemptionFlowPreparationService;
use LBHurtado\XChange\Services\DefaultRedemptionProcessorService;
use LBHurtado\XChange\Services\DefaultRedemptionValidationService;
use LBHurtado\XChange\Services\DefaultWithdrawalExecutionService;
use LBHurtado\XChange\Services\DefaultWithdrawalProcessorService;
use LBHurtado\XChange\Services\DefaultWithdrawalValidationService;
use LBHurtado\XChange\Services\NullRedemptionCompletionStore;

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

        $this->app->bind(RedemptionFlowPreparationContract::class, function ($app) {
            $service = config('x-change.services.redemption_flow_preparation', DefaultRedemptionFlowPreparationService::class);

            return $app->make($service);
        });

        $this->app->bind(RedemptionCompletionStoreContract::class, function ($app) {
            $service = config('x-change.services.redemption_completion_store', NullRedemptionCompletionStore::class);

            return $app->make($service);
        });

        $this->app->bind(RedemptionCompletionContextContract::class, function ($app) {
            $service = config('x-change.services.redemption_completion_context', DefaultRedemptionCompletionContextService::class);

            return $app->make($service);
        });

        $this->app->bind(RedemptionContextResolverContract::class, function ($app) {
            $service = config('x-change.services.redemption_context_resolver', DefaultRedemptionContextResolverService::class);

            return $app->make($service);
        });

        $this->app->bind(RedemptionValidationContract::class, function ($app) {
            $service = config('x-change.services.redemption_validation', DefaultRedemptionValidationService::class);

            return $app->make($service);
        });

        $this->app->bind(RedemptionProcessorContract::class, function ($app) {
            $service = config('x-change.services.redemption_processor', DefaultRedemptionProcessorService::class);

            return $app->make($service);
        });

        $this->app->bind(RedemptionExecutionContract::class, function ($app) {
            $service = config('x-change.services.redemption_execution', DefaultRedemptionExecutionService::class);

            return $app->make($service);
        });

        $this->app->bind(ClaimExecutionFactoryContract::class, function ($app) {
            $service = config('x-change.services.claim_execution_factory', DefaultClaimExecutionFactory::class);

            return $app->make($service);
        });

        $this->app->bind(WithdrawalValidationContract::class, function ($app) {
            $service = config('x-change.services.withdrawal_validation', DefaultWithdrawalValidationService::class);

            return $app->make($service);
        });

        $this->app->bind(WithdrawalProcessorContract::class, function ($app) {
            $service = config('x-change.services.withdrawal_processor', DefaultWithdrawalProcessorService::class);

            return $app->make($service);
        });

        $this->app->bind(WithdrawalExecutionContract::class, function ($app) {
            $service = config('x-change.services.withdrawal_execution', DefaultWithdrawalExecutionService::class);

            return $app->make($service);
        });

        $this->app->bind(DisbursementReconciliationStoreContract::class, function ($app) {
            $service = config(
                'x-change.services.disbursement_reconciliation_store',
                DefaultDisbursementReconciliationStore::class
            );

            return $app->make($service);
        });

        $this->app->bind(DisbursementStatusResolverContract::class, function ($app) {
            $service = config(
                'x-change.services.disbursement_status_resolver',
                DefaultDisbursementStatusResolverService::class
            );

            return $app->make($service);
        });

        $this->app->bind(DisbursementStatusFetcherContract::class, function ($app) {
            $service = config(
                'x-change.services.disbursement_status_fetcher',
                DefaultDisbursementStatusFetcherService::class,
            );

            return $app->make($service);
        });

        $this->app->bind(DisbursementReconciliationContract::class, function ($app) {
            $service = config(
                'x-change.services.disbursement_reconciliation',
                DefaultDisbursementReconciliationService::class,
            );

            return $app->make($service);
        });
    }

    public function boot(): void
    {
        $this->bootConfig();
        $this->bootRoutes();
        $this->bootExceptionRendering();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ReconcilePendingDisbursementsCommand::class,
            ]);
        }
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

        $exceptions->renderable(function (IdempotencyConflict $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->errorFromThrowable(
                $e,
                'IDEMPOTENCY_CONFLICT',
                [],
                409,
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
