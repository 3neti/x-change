<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Scenarios;

use Illuminate\Console\Command;
use Throwable;

final class LifecycleScenarioGroupRunner
{
    public function __construct(
        private readonly LifecycleScenarioGroupRepository $groups,
        private readonly LifecycleScenarioEngine $engine,
    ) {}

    public function run(
        Command $command,
        string $groupKey,
        LifecycleScenarioRunOptions $options,
        bool $continueOnFailure = true,
    ): LifecycleScenarioGroupRunResult {
        $scenarios = $this->groups->scenariosFor($groupKey);

        $results = [];
        $failures = [];

        foreach (array_keys($scenarios) as $scenarioKey) {
            try {
                $scenarioOptions = new LifecycleScenarioRunOptions(
                    issuer: $options->issuer,
                    wallet: $options->wallet,
                    amount: $options->amount,
                    timeout: $options->timeout,
                    poll: $options->poll,
                    maxPolls: $options->maxPolls,
                    onlyAttempt: $options->onlyAttempt,
                    noClaim: $options->noClaim,
                    json: $options->json,
                    acceptPending: $options->acceptPending,
                );

                $result = $this->engine->run(
                    command: $command,
                    scenarioKey: $scenarioKey,
                    options: $scenarioOptions,
                );

                $results[$scenarioKey] = $result;

                if ($result->exitCode !== Command::SUCCESS && ! $continueOnFailure) {
                    break;
                }
            } catch (Throwable $exception) {
                $failures[] = [
                    'scenario' => $scenarioKey,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ];

                if (! $continueOnFailure) {
                    break;
                }
            }
        }

        return new LifecycleScenarioGroupRunResult(
            group: $groupKey,
            results: $results,
            failures: $failures,
        );
    }
}
