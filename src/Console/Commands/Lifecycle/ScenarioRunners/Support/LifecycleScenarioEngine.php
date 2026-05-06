<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support;

use Illuminate\Console\Command;
use InvalidArgumentException;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\ScenarioRunContext;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\ScenarioRunnerRegistry;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;

final class LifecycleScenarioEngine
{
    public function __construct(
        private readonly LifecycleScenarioRepository $scenarioRepository,
        private readonly LifecycleScenarioBootstrapper $bootstrapper,
        private readonly ScenarioRunnerRegistry $registry,
        private readonly SubmitPayCodeClaim $submitPayCodeClaim,
        private readonly SettlementEnvelopeReadinessContract $settlementEnvelopeReadiness,
        private readonly WalletTransactionSnapshot $walletTransactions,
    ) {}

    public function run(
        Command $command,
        string $scenarioKey,
        LifecycleScenarioRunOptions $options,
    ): LifecycleScenarioEngineResult {
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

        $claims = data_get($scenario, 'claims');

        if (is_array($claims)) {
            return $this->runSequentialClaimsScenario(
                command: $command,
                scenarioKey: $scenarioKey,
                scenario: $scenario,
                claims: $claims,
                options: $options,
            );
        }

        try {
            $attempts = $this->scenarioRepository->attemptsFor(
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

        if (! $options->json) {
            $command->info("Running scenario: {$scenarioKey}");

            if ($options->onlyAttempt !== null) {
                $command->line('Selected attempt: '.$options->onlyAttempt);
            }

            $command->line('Estimating cost...');
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

        if (! $options->json) {
            $command->line('Generating voucher...');
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

        $mode = $this->scenarioRepository->modeFor($scenario);

        if ($this->registry->has($mode)) {
            $result = $this->registry->for($mode)->run(
                new ScenarioRunContext(
                    command: $command,
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

        return new LifecycleScenarioEngineResult(
            exitCode: Command::FAILURE,
            payload: [
                'success' => false,
                'message' => "No lifecycle scenario runner registered for mode [{$mode}].",
                'scenario' => $scenarioKey,
                'mode' => $mode,
            ],
        );
    }

    /**
     * This remains command-adjacent for now because sequential claim execution still lives
     * inside RunLifecycleScenarioCommand.
     *
     * In the next slice, extract SequentialClaimsScenarioRunner and delete this bridge.
     *
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $claims
     */
    private function runSequentialClaimsScenario(
        Command $command,
        string $scenarioKey,
        array $scenario,
        array $claims,
        LifecycleScenarioRunOptions $options,
    ): LifecycleScenarioEngineResult {
        if (! $options->json) {
            $command->info("Running scenario: {$scenarioKey}");
            $command->line('Estimating cost...');
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

        if (! $options->json) {
            $command->line('Generating voucher...');
        }

        /**
         * Temporary bridge:
         *
         * We intentionally return a special payload instead of duplicating the current
         * command's runSequentialClaimsScenario() logic here. The command will detect
         * this bridge payload and delegate to its existing method for now.
         */
        return new LifecycleScenarioEngineResult(
            exitCode: Command::SUCCESS,
            payload: [
                '_bridge' => 'sequential_claims',
                'scenario_key' => $scenarioKey,
                'scenario' => $scenario,
                'claims' => $claims,
                'bootstrap' => $bootstrap,
            ],
        );
    }
}
