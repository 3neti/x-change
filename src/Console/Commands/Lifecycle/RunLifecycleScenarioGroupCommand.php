<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle;

use Illuminate\Console\Command;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioGroupRunner;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRunOptions;

final class RunLifecycleScenarioGroupCommand extends Command
{
    protected $signature = 'xchange:lifecycle:run-group
        {group : Scenario group key or category}
        {--issuer= : Issuer identifier override}
        {--wallet= : Wallet identifier override}
        {--amount= : Amount override}
        {--timeout= : Timeout seconds for each scenario}
        {--poll= : Poll interval seconds for each scenario}
        {--max-polls= : Maximum poll attempts for each scenario}
        {--only-attempt= : Selected attempt to run for each scenario}
        {--no-claim : Issue vouchers without submitting claims}
        {--accept-pending : Treat pending provider outcomes as acceptable}
        {--stop-on-failure : Stop after the first failing scenario}
        {--json : Render JSON output}';

    protected $description = 'Run a lifecycle scenario group.';

    public function handle(LifecycleScenarioGroupRunner $runner): int
    {
        $group = (string) $this->argument('group');

        $options = LifecycleScenarioRunOptions::fromConsoleOptions($this->options());

        try {
            $result = $runner->run(
                command: $this,
                groupKey: $group,
                options: $options,
                continueOnFailure: ! (bool) $this->option('stop-on-failure'),
            );
        } catch (\InvalidArgumentException $exception) {
            if ($options->json) {
                $this->line(json_encode([
                    'group' => $group,
                    'successful' => false,
                    'message' => $exception->getMessage(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return self::FAILURE;
            }

            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($options->json) {
            $this->line(json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $result->successful() ? self::SUCCESS : self::FAILURE;
        }

        $this->info(sprintf('Lifecycle scenario group: %s', $group));
        $this->line(sprintf(
            'Summary: %d total, %d passed, %d failed',
            $result->total(),
            $result->passed(),
            $result->failed(),
        ));

        foreach ($result->results as $scenario => $scenarioResult) {
            $status = $scenarioResult->exitCode === self::SUCCESS ? 'PASS' : 'FAIL';

            $this->line(sprintf('[%s] %s', $status, $scenario));
        }

        foreach ($result->failures as $failure) {
            $this->error(sprintf(
                '[ERROR] %s: %s',
                $failure['scenario'] ?? 'unknown',
                $failure['message'] ?? 'Unknown error',
            ));
        }

        return $result->successful() ? self::SUCCESS : self::FAILURE;
    }
}
