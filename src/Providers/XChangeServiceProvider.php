<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Providers;

use App\Models\User;
use FrittenKeeZ\Vouchers\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LBHurtado\Cash\Contracts\WithdrawalIntervalEnforcerContract;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\PaymentGateway\Adapters\NetbankPayoutProvider;
use LBHurtado\PaymentGateway\Contracts\WalletProxy;
use LBHurtado\ReportRegistry\Contracts\ReportResolverInterface;
use LBHurtado\Voucher\Events\VoucherDisbursementFailed;
use LBHurtado\Voucher\Events\VoucherDisbursementSucceeded;
use LBHurtado\XChange\Console\Commands\Claim\LoadPayCodeRedemptionCompletionContextCommand;
use LBHurtado\XChange\Console\Commands\Claim\PreparePayCodeRedemptionFlowCommand;
use LBHurtado\XChange\Console\Commands\Claim\SubmitPayCodeClaimCommand;
use LBHurtado\XChange\Console\Commands\Disbursement\CheckDisbursementStatusCommand;
use LBHurtado\XChange\Console\Commands\Lifecycle\PrepareLifecycleEnvironmentCommand;
use LBHurtado\XChange\Console\Commands\Lifecycle\RunLifecycleScenarioCommand;
use LBHurtado\XChange\Console\Commands\Onboarding\OnboardIssuerCommand;
use LBHurtado\XChange\Console\Commands\Onboarding\OpenIssuerWalletCommand;
use LBHurtado\XChange\Console\Commands\PayCode\EstimatePayCodeCostCommand;
use LBHurtado\XChange\Console\Commands\PayCode\GeneratePayCodeCommand;
use LBHurtado\XChange\Console\Commands\ReconcilePendingDisbursementsCommand;
use LBHurtado\XChange\Console\Commands\Revenue\CollectRevenueCommand;
use LBHurtado\XChange\Console\Commands\Revenue\ShowPendingRevenueCommand;
use LBHurtado\XChange\Console\Commands\Wallet\GetWalletBalanceCommand;
use LBHurtado\XChange\Contracts\ApprovalWorkflowContract;
use LBHurtado\XChange\Contracts\ClaimApprovalExecutionContract;
use LBHurtado\XChange\Contracts\ClaimApprovalInitiationContract;
use LBHurtado\XChange\Contracts\ClaimApprovalNotificationContract;
use LBHurtado\XChange\Contracts\ClaimApprovalWorkflowStoreContract;
use LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract;
use LBHurtado\XChange\Contracts\ClaimOtpChallengeContract;
use LBHurtado\XChange\Contracts\DisbursementReconciliationContract;
use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Contracts\DisbursementStatusFetcherContract;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;
use LBHurtado\XChange\Contracts\EventLifecycleServiceContract;
use LBHurtado\XChange\Contracts\EventStoreContract;
use LBHurtado\XChange\Contracts\PricelistServiceContract;
use LBHurtado\XChange\Contracts\PricingServiceContract;
use LBHurtado\XChange\Contracts\ReconciliationLifecycleServiceContract;
use LBHurtado\XChange\Contracts\RedemptionCompletionContextContract;
use LBHurtado\XChange\Contracts\RedemptionCompletionStoreContract;
use LBHurtado\XChange\Contracts\RedemptionContextResolverContract;
use LBHurtado\XChange\Contracts\RedemptionExecutionContract;
use LBHurtado\XChange\Contracts\RedemptionFlowPreparationContract;
use LBHurtado\XChange\Contracts\RedemptionProcessorContract;
use LBHurtado\XChange\Contracts\RedemptionValidationContract;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Contracts\SettlementExecutionContract;
use LBHurtado\XChange\Contracts\SettlementFlowPreparationContract;
use LBHurtado\XChange\Contracts\UserLifecycleServiceContract;
use LBHurtado\XChange\Contracts\VendorRegistryContract;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Contracts\WithdrawalExecutionContract;
use LBHurtado\XChange\Contracts\WithdrawalLifecycleServiceContract;
use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;
use LBHurtado\XChange\Contracts\WithdrawalProcessorContract;
use LBHurtado\XChange\Contracts\WithdrawalValidationContract;
use LBHurtado\XChange\Events\DisbursementConfirmed;
use LBHurtado\XChange\Exceptions\IdempotencyConflict;
use LBHurtado\XChange\Exceptions\InsufficientWalletBalance;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;
use LBHurtado\XChange\Exceptions\PayCodeIssuerNotResolved;
use LBHurtado\XChange\Exceptions\PayCodeWalletNotResolved;
use LBHurtado\XChange\Listeners\HandleConfirmedDisbursement;
use LBHurtado\XChange\Listeners\RecordFailedVoucherDisbursement;
use LBHurtado\XChange\Listeners\RecordSuccessfulVoucherDisbursement;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\CacheClaimApprovalWorkflowStore;
use LBHurtado\XChange\Services\ConfigVendorRegistry;
use LBHurtado\XChange\Services\DefaultApprovalWorkflowService;
use LBHurtado\XChange\Services\DefaultClaimApprovalExecutionService;
use LBHurtado\XChange\Services\DefaultClaimApprovalInitiationService;
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
use LBHurtado\XChange\Services\DefaultSettlementExecutionService;
use LBHurtado\XChange\Services\DefaultSettlementFlowPreparationService;
use LBHurtado\XChange\Services\DefaultVoucherFlowCapabilityResolver;
use LBHurtado\XChange\Services\DefaultWithdrawalExecutionService;
use LBHurtado\XChange\Services\DefaultWithdrawalProcessorService;
use LBHurtado\XChange\Services\DefaultWithdrawalValidationService;
use LBHurtado\XChange\Services\EventLifecycleService;
use LBHurtado\XChange\Services\InstructionBackedPricingService;
use LBHurtado\XChange\Services\NullClaimApprovalNotificationService;
use LBHurtado\XChange\Services\NullClaimOtpChallengeService;
use LBHurtado\XChange\Services\NullRedemptionCompletionStore;
use LBHurtado\XChange\Services\NullSettlementEnvelopeReadinessService;
use LBHurtado\XChange\Services\NullWithdrawalOtpApprovalService;
use LBHurtado\XChange\Services\PricelistService;
use LBHurtado\XChange\Services\ReconciliationLifecycleService;
use LBHurtado\XChange\Services\SystemWalletProxy;
use LBHurtado\XChange\Services\TxtcmdrWithdrawalOtpApprovalService;
use LBHurtado\XChange\Services\UserLifecycleService;
use LBHurtado\XChange\Services\VoucherAccessService;
use LBHurtado\XChange\Services\VoucherLifecycleService;
use LBHurtado\XChange\Services\WithdrawalLifecycleService;
use LBHurtado\XChange\Services\WithdrawalPipeline;
use LBHurtado\XChange\Services\XChangeWithdrawalIntervalEnforcer;
use LBHurtado\XChange\Support\Logging\CacheEventStore;

class XChangeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            $this->packagePath('config/x-change.php'),
            'x-change'
        );

        $this->alignWalletDefaults();
        $this->alignVoucherDefaults();
        $this->alignAccountSystemUser();

        $this->registerServices();
        $this->registerIntegrations();
        $this->registerServiceContracts();
        $this->registerIntegrationContracts();
        $this->registerReportDriverSource();

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

        $this->app->bind(PricingServiceContract::class, InstructionBackedPricingService::class);

        $this->app->bind(WalletProxy::class, function ($app) {
            $service = config(
                'x-change.payout.wallet_proxy',
                SystemWalletProxy::class
            );

            return $app->make($service);
        });

        $this->app->bind(PricelistServiceContract::class, PricelistService::class);

        $this->app->bind(VoucherAccessContract::class, VoucherAccessService::class);
        $this->app->bind(VoucherLifecycleServiceContract::class, VoucherLifecycleService::class);

        $this->app->bind(ReconciliationLifecycleServiceContract::class, ReconciliationLifecycleService::class);

        $this->app->bind(EventLifecycleServiceContract::class, EventLifecycleService::class);

        $this->app->singleton(EventStoreContract::class, CacheEventStore::class);

        $this->app->bind(
            WithdrawalLifecycleServiceContract::class,
            (string) config('x-change.lifecycle.withdrawals.service', WithdrawalLifecycleService::class),
        );

        $this->app->bind(UserLifecycleServiceContract::class, UserLifecycleService::class);

        $this->app->singleton(PayoutProvider::class, function ($app) {
            $provider = config(
                'x-change.payout.provider',
                NetbankPayoutProvider::class
            );

            return $app->make($provider);
        });

        $this->app->bind(
            WithdrawalIntervalEnforcerContract::class,
            XChangeWithdrawalIntervalEnforcer::class,
        );

        $this->app->bind(WithdrawalPipeline::class, function ($app) {
            $steps = config('x-change.withdrawal.pipeline.steps', []);

            foreach ($steps as $step) {
                if (! is_string($step) || ! class_exists($step)) {
                    throw new InvalidArgumentException("Invalid withdrawal pipeline step: {$step}");
                }
            }

            return new WithdrawalPipeline(
                pipeline: $app->make(Pipeline::class),
                steps: $steps,
            );
        });

        $this->app->bind(
            WithdrawalIntervalEnforcerContract::class,
            XChangeWithdrawalIntervalEnforcer::class,
        );

        $this->app->bind(WithdrawalOtpApprovalServiceContract::class, function ($app) {
            return match (config('x-change.withdrawal.otp.driver', 'null')) {
                'txtcmdr' => $app->make(TxtcmdrWithdrawalOtpApprovalService::class),
                'null' => $app->make(NullWithdrawalOtpApprovalService::class),
                default => throw new InvalidArgumentException(
                    'Unsupported withdrawal OTP driver: '.config('x-change.withdrawal.otp.driver')
                ),
            };
        });

        $this->app->bind(VendorRegistryContract::class, function ($app) {
            return match (config('x-change.vendors.registry', 'config')) {
                'config' => $app->make(ConfigVendorRegistry::class),

                default => throw new InvalidArgumentException(
                    'Unsupported vendor registry: '.config('x-change.vendors.registry')
                ),
            };
        });

        $this->app->bind(ApprovalWorkflowContract::class, function ($app) {
            $handlers = [];

            foreach (config('x-change.approval_workflow.handlers', []) as $requirement => $handlerClass) {
                $handler = $app->make($handlerClass);
                $handlers[$requirement] = $handler;
            }

            return new DefaultApprovalWorkflowService($handlers);
        });

        $this->app->bind(
            VoucherFlowCapabilityResolverContract::class,
            DefaultVoucherFlowCapabilityResolver::class,
        );

        $this->app->bind(
            SettlementFlowPreparationContract::class,
            DefaultSettlementFlowPreparationService::class,
        );

        $this->app->bind(
            SettlementEnvelopeReadinessContract::class,
            NullSettlementEnvelopeReadinessService::class,
        );

        $this->app->bind(
            SettlementExecutionContract::class,
            DefaultSettlementExecutionService::class,
        );

        $this->app->bind(
            ClaimApprovalWorkflowStoreContract::class,
            CacheClaimApprovalWorkflowStore::class,
        );

        $this->app->bind(
            ClaimApprovalExecutionContract::class,
            DefaultClaimApprovalExecutionService::class,
        );

        $this->app->bind(
            ClaimApprovalInitiationContract::class,
            DefaultClaimApprovalInitiationService::class,
        );

        $this->app->bind(
            ClaimApprovalNotificationContract::class,
            NullClaimApprovalNotificationService::class,
        );

        $this->app->bind(
            ClaimOtpChallengeContract::class,
            NullClaimOtpChallengeService::class,
        );
    }

    public function boot(): void
    {
        $this->bootConfig();
        $this->bootRoutes();
        $this->bootExceptionRendering();
        $this->bootMigrations();

        if ($this->app->runningInConsole()) {
            $this->commands([
                OnboardIssuerCommand::class,
                OpenIssuerWalletCommand::class,
                GetWalletBalanceCommand::class,
                EstimatePayCodeCostCommand::class,
                GeneratePayCodeCommand::class,
                PreparePayCodeRedemptionFlowCommand::class,
                LoadPayCodeRedemptionCompletionContextCommand::class,
                SubmitPayCodeClaimCommand::class,
                CheckDisbursementStatusCommand::class,
                ReconcilePendingDisbursementsCommand::class,

                PrepareLifecycleEnvironmentCommand::class,
                RunLifecycleScenarioCommand::class,
                CollectRevenueCommand::class,
                ShowPendingRevenueCommand::class,
            ]);
        }

        Event::listen(
            VoucherDisbursementSucceeded::class,
            RecordSuccessfulVoucherDisbursement::class
        );

        Event::listen(
            VoucherDisbursementFailed::class,
            RecordFailedVoucherDisbursement::class
        );

        Event::listen(
            DisbursementConfirmed::class,
            HandleConfirmedDisbursement::class
        );
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

    protected function registerReportDriverSource(): void
    {
        if (! interface_exists(ReportResolverInterface::class)) {
            return;
        }

        $sources = $this->app['config']->get('report-registry.driver_sources', []);
        $path = $this->packagePath('resources/report-drivers');

        if (! in_array($path, $sources, true)) {
            $sources[] = $path;
            $this->app['config']->set('report-registry.driver_sources', $sources);
        }
    }

    protected function bootConfig(): void
    {
        $this->publishes([
            $this->packagePath('config/x-change.php') => config_path('x-change.php'),
        ], 'x-change-config');

        $this->publishes([
            $this->packagePath('stubs/scripts/test-netbank-lifecycle.sh.stub') => base_path('scripts/test-netbank-lifecycle.sh'),
            $this->packagePath('stubs/scripts/.xchange-lifecycle.env.example') => base_path('scripts/.xchange-lifecycle.env.example'),
        ], 'x-change-scripts');
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

        // New lifecycle API surface for Scramble / public API.
        $this->loadRoutesFrom(__DIR__.'/../../routes/lifecycle-api.php');
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

    protected function bootMigrations(): void
    {
        $this->loadMigrationsFrom($this->packagePath('database/migrations'));
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

    protected function alignWalletDefaults(): void
    {
        $slug = config('x-change.payout.system_wallet_slug')
            ?? config('x-change.onboarding.default_wallet_slug')
            ?? 'platform';

        $name = config('x-change.onboarding.default_wallet_name', 'Platform Wallet');

        config()->set('wallet.wallet.default.slug', $slug);
        config()->set('wallet.wallet.default.name', $name);
    }

    protected function alignVoucherDefaults(): void
    {
        $currentVoucherModel = config('vouchers.models.voucher');

        if (! is_string($currentVoucherModel)
            || $currentVoucherModel === ''
            || $currentVoucherModel === Voucher::class) {
            config()->set(
                'vouchers.models.voucher',
                \LBHurtado\Voucher\Models\Voucher::class
            );
        }
    }

    protected function alignAccountSystemUser(): void
    {
        $currentIdentifier = config('account.system_user.identifier');
        $currentColumn = config('account.system_user.identifier_column');
        $currentModel = config('account.system_user.model');

        $walletDefaultIdentifier = env('SYSTEM_USER_ID', 'lester@hurtado.ph');
        $walletDefaultColumn = 'email';
        $walletDefaultModel = User::class;

        if ($currentModel === $walletDefaultModel) {
            config()->set(
                'account.system_user.model',
                config('x-change.onboarding.issuer_model', User::class)
            );
        }

        if ($currentIdentifier === $walletDefaultIdentifier) {
            config()->set(
                'account.system_user.identifier',
                config('x-change.payout.system_user_id')
            );
        }

        if ($currentColumn === $walletDefaultColumn) {
            config()->set(
                'account.system_user.identifier_column',
                config('x-change.payout.system_user_column', 'id')
            );
        }
    }
}
