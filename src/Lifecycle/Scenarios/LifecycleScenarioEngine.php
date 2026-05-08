<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Scenarios;

use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiPaynamicsConstellation\Contracts\ConstellationOtpResolver;
use LBHurtado\EmiPaynamicsConstellation\Support\InteractiveOtpResolver;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Lifecycle\Output\ConsoleLifecycleOutput;
use LBHurtado\XChange\Lifecycle\Output\LifecycleOutputContract;
use LBHurtado\XChange\Lifecycle\Runners\ScenarioRunContext;
use LBHurtado\XChange\Lifecycle\Runners\ScenarioRunnerResolver;
use LBHurtado\XChange\Lifecycle\Runners\Support\LifecycleUserSummary;
use LBHurtado\XChange\Lifecycle\Runners\Support\WalletTransactionSnapshot;
use RuntimeException;

final class LifecycleScenarioEngine
{
    public function __construct(
        private readonly LifecycleScenarioRepository $scenarioRepository,
        private readonly LifecycleScenarioBootstrapper $bootstrapper,
        private readonly ScenarioRunnerResolver $resolver,
        private readonly SettlementEnvelopeReadinessContract $settlementEnvelopeReadiness,
        private readonly WalletTransactionSnapshot $walletTransactions,
        private readonly Container $container,
    ) {}

    public function run(
        Command $command,
        string $scenarioKey,
        LifecycleScenarioRunOptions $options,
        ?LifecycleOutputContract $output = null,
    ): LifecycleScenarioEngineResult {
        $output ??= new ConsoleLifecycleOutput($command);

        try {
            $scenario = $this->scenarioRepository->findOrFail($scenarioKey);
        } catch (InvalidArgumentException $e) {
            return new LifecycleScenarioEngineResult(
                exitCode: Command::FAILURE,
                payload: [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'scenario' => $scenarioKey,
                ],
            );
        }

        $scenario = array_replace_recursive(
            (array) config('x-change.lifecycle.defaults', []),
            $scenario,
        );

        try {
            $resolvedProvider = $this->resolveProvider($options, $scenario, $output);
        } catch (InvalidArgumentException $e) {
            return new LifecycleScenarioEngineResult(
                exitCode: Command::FAILURE,
                payload: [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'scenario' => $scenarioKey,
                ],
            );
        }

        if ($output->isJson() && $this->requiresInteractiveOtp()) {
            return new LifecycleScenarioEngineResult(
                exitCode: Command::FAILURE,
                payload: [
                    'success' => false,
                    'message' => 'Cannot use --json with a provider that requires interactive OTP. '
                        .'Remove --json or set CONSTELLATION_OTP_RESOLVER=null.',
                    'scenario' => $scenarioKey,
                ],
            );
        }

        try {
            $resolution = $this->resolver->resolve($scenario);
        } catch (RuntimeException $e) {
            return new LifecycleScenarioEngineResult(
                exitCode: Command::FAILURE,
                payload: [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'scenario' => $scenarioKey,
                    'mode' => (string) data_get($scenario, 'mode', 'default'),
                ],
            );
        }

        $scenario = $resolution->scenario;
        $mode = $resolution->mode;

        try {
            $attempts = $mode === 'sequential_claims'
                ? []
                : $this->scenarioRepository->attemptsFor(
                    scenario: $scenario,
                    selectedAttempt: $options->onlyAttempt,
                );
        } catch (InvalidArgumentException $e) {
            return new LifecycleScenarioEngineResult(
                exitCode: Command::FAILURE,
                payload: [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'scenario' => $scenarioKey,
                    'selected_attempt' => $options->onlyAttempt,
                ],
            );
        }

        if (! $output->isJson()) {
            $output->info("Running scenario: {$scenarioKey}");

            if ($options->onlyAttempt !== null) {
                $output->line('Selected attempt: '.$options->onlyAttempt);
            }

            $output->line('Estimating cost...');
        }

        $bootstrap = $this->bootstrapper->bootstrap(
            scenario: $scenario,
            issuerOption: $options->issuer,
            walletOption: $options->wallet,
            amountOption: $options->amount,
            timeoutOption: $options->timeout,
            pollOption: $options->poll,
            maxPollsOption: $options->maxPolls,
        );

        if (! $output->isJson()) {
            $output->line('Generating voucher...');
        }

        if ($options->noClaim) {
            $recentTransactions = $this->walletTransactions->recentFor(
                issuer: $bootstrap->issuer,
                idempotencyKey: $bootstrap->idempotencyKey,
                voucherCode: $bootstrap->generated->code,
                limit: 10,
            );

            return new LifecycleScenarioEngineResult(
                exitCode: Command::SUCCESS,
                payload: [
                    'scenario' => $scenarioKey,
                    'label' => $scenario['label'] ?? $scenarioKey,
                    'selected_attempt' => $options->onlyAttempt,
                    'issuer' => app(LifecycleUserSummary::class)->fromModel($bootstrap->issuer),
                    'claim_mobile' => $bootstrap->baseClaimMobile,
                    'attempts' => array_keys($attempts),
                    'attempt_summary' => [
                        'passed' => 0,
                        'failed' => 0,
                        'total' => count($attempts),
                    ],
                    'estimate' => $bootstrap->estimate,
                    'generated' => $bootstrap->generated->toArray(),
                    'wallet_transactions' => $recentTransactions,
                ],
            );
        }

        $scenario['_runtime'] = [
            ...(array) data_get($scenario, '_runtime', []),
            'selected_attempt' => $options->onlyAttempt,
            'timeout' => $bootstrap->timeout,
            'poll' => $bootstrap->poll,
            'max_polls' => $bootstrap->maxPolls,
        ];

        $result = $resolution->runner->run(
            new ScenarioRunContext(
                output: $output,
                scenarioKey: $scenarioKey,
                scenario: $scenario,
                issuer: $bootstrap->issuer,
                generated: $bootstrap->generated,
                voucher: $bootstrap->voucher,
                attempts: $attempts,
                baseClaimMobile: $bootstrap->baseClaimMobile,
                estimate: $bootstrap->estimate,
                idempotencyKey: $bootstrap->idempotencyKey,
                readiness: $this->settlementEnvelopeReadiness,
            )
        );

        $payload = $result->payload;

        if ($resolvedProvider !== null) {
            $payload['provider'] = $resolvedProvider;
        }

        return new LifecycleScenarioEngineResult(
            exitCode: $result->exitCode,
            payload: $payload,
        );
    }

    /**
     * Resolve and rebind the payout provider.
     *
     * Precedence: CLI/API option → scenario config → container default.
     *
     * @return string|null The resolved provider label, or null if unchanged.
     */
    private function resolveProvider(
        LifecycleScenarioRunOptions $options,
        array $scenario,
        LifecycleOutputContract $output,
    ): ?string {
        $label = $options->provider;

        if ($label === null || $label === '') {
            $label = data_get($scenario, 'provider');
        }

        if (! is_string($label) || $label === '') {
            return null;
        }

        $class = config("emi.payout_providers.{$label}");

        if (! is_string($class) || ! class_exists($class)) {
            throw new InvalidArgumentException(
                "Unknown payout provider [{$label}]. Available: "
                .implode(', ', array_keys((array) config('emi.payout_providers', [])))
            );
        }

        $this->container->singleton(PayoutProvider::class, fn ($app) => $app->make($class));

        if (! $output->isJson()) {
            $output->line("Provider: {$label} ({$class})");
        }

        return $label;
    }

    private function requiresInteractiveOtp(): bool
    {
        // Only relevant when the active payout provider is Constellation.
        $activeProvider = $this->container->make(PayoutProvider::class);

        if (! $activeProvider instanceof \LBHurtado\EmiPaynamicsConstellation\Adapters\ConstellationPayoutProvider) {
            return false;
        }

        if (! interface_exists(ConstellationOtpResolver::class)) {
            return false;
        }

        if (! $this->container->bound(ConstellationOtpResolver::class)) {
            return false;
        }

        return $this->container->make(ConstellationOtpResolver::class) instanceof InteractiveOtpResolver;
    }
}
