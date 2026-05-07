<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support;

use Illuminate\Console\Command;
use InvalidArgumentException;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\ScenarioRunContext;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\ScenarioRunnerResolver;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use RuntimeException;

final class LifecycleScenarioEngine
{
    public function __construct(
        private readonly LifecycleScenarioRepository $scenarioRepository,
        private readonly LifecycleScenarioBootstrapper $bootstrapper,
        private readonly ScenarioRunnerResolver $resolver,
        private readonly SettlementEnvelopeReadinessContract $settlementEnvelopeReadiness,
        private readonly WalletTransactionSnapshot $walletTransactions,
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

        return new LifecycleScenarioEngineResult(
            exitCode: $result->exitCode,
            payload: $result->payload,
        );
    }
}
