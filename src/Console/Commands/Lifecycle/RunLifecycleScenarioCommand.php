<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\XChange\Lifecycle\Output\ConsoleLifecycleOutput;
use LBHurtado\XChange\Lifecycle\Runners\Support\LifecycleDisbursementPoller;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioEngine;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRepository;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRunOptions;

class RunLifecycleScenarioCommand extends Command
{
    protected $signature = 'xchange:lifecycle:run
      {scenario? : Scenario key from x-change.lifecycle.scenarios}
        {--list : List available scenarios}
        {--provider= : Provider label from emi.payout_providers config}
        {--issuer= : Issuer user id}
        {--wallet= : Wallet owner/user id}
        {--amount= : Override scenario amount}
        {--prepare : Run xchange:lifecycle:prepare first}
        {--fresh : Prepare with migrate:fresh and lifecycle seeders}
        {--no-claim : Generate but do not claim}
        {--check-only= : Existing voucher code to check only}
        {--timeout= : Poll timeout in seconds}
        {--poll= : Poll interval in seconds}
        {--max-polls= : Maximum number of polling attempts}
        {--accept-pending : Treat a trusted pending provider transaction as good enough}
        {--only-attempt= : Run only one named attempt from the scenario}
        {--json : Output JSON}';

    protected $description = 'Run a named lifecycle scenario.';

    public function handle(
        LifecycleScenarioEngine $engine,
        LifecycleResultRenderer $renderer,
        LifecycleScenarioRepository $scenarioRepository,
    ): int {
        if ($this->option('list')) {
            return $this->listScenarios($scenarioRepository);
        }

        if ($this->option('prepare') || $this->option('fresh')) {
            Artisan::call('xchange:lifecycle:prepare', array_filter([
                '--fresh' => (bool) $this->option('fresh'),
                '--seed' => true,
            ]));

            $this->line(Artisan::output());
        }

        $existingCode = $this->option('check-only');

        if (is_string($existingCode) && trim($existingCode) !== '') {
            return $this->runCheckOnly(trim($existingCode), $renderer);
        }

        $scenarioKey = (string) $this->argument('scenario');

        $options = LifecycleScenarioRunOptions::fromConsoleOptions($this->options());

        try {
            $this->rebindProviderIfRequested($options);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $output = new ConsoleLifecycleOutput($this);

        $result = $engine->run(
            command: $this,
            scenarioKey: $scenarioKey,
            options: $options,
            output: $output,
        );

        return $renderer->render(
            command: $this,
            payload: $result->payload,
            exitCode: $result->exitCode,

        );
    }

    protected function listScenarios(LifecycleScenarioRepository $scenarioRepository): int
    {
        $scenarios = $scenarioRepository->all();

        if ($scenarios === []) {
            $this->warn('No lifecycle scenarios found.');

            return self::SUCCESS;
        }

        $this->info('Available lifecycle scenarios:');

        foreach ($scenarios as $key => $scenario) {
            $this->line(sprintf(
                ' - %s (%s)',
                $key,
                $scenarioRepository->labelFor((string) $key, (array) $scenario),
            ));
        }

        return self::SUCCESS;
    }

    protected function runCheckOnly(string $code, LifecycleResultRenderer $renderer): int
    {
        $timeout = (int) ($this->option('timeout') ?: config('x-change.lifecycle.defaults.timeout', 180));
        $poll = max(1, (int) ($this->option('poll') ?: config('x-change.lifecycle.defaults.poll', 10)));
        $maxPolls = $this->resolveMaxPolls($timeout, $poll);

        if (! $this->option('json')) {
            $this->info("Checking existing voucher: {$code}");
        }

        $payload = app(LifecycleDisbursementPoller::class)->poll(
            code: $code,
            timeout: $timeout,
            poll: $poll,
            maxPolls: $maxPolls,
            acceptPending: (bool) $this->option('accept-pending'),
        );

        return $renderer->render(
            command: $this,
            payload: [
                'mode' => 'check-only',
                'voucher_code' => $code,
                'disbursement_check' => $payload,
            ],
        );
    }

    protected function rebindProviderIfRequested(LifecycleScenarioRunOptions $options): void
    {
        $label = $options->provider;

        if ($label === null || $label === '') {
            return;
        }

        $class = config("emi.payout_providers.{$label}");

        if (! is_string($class) || ! class_exists($class)) {
            throw new InvalidArgumentException(
                "Unknown payout provider [{$label}]. Available: "
                .implode(', ', array_keys((array) config('emi.payout_providers', [])))
            );
        }

        $this->laravel->singleton(PayoutProvider::class, fn ($app) => $app->make($class));

        if (! $this->option('json')) {
            $this->line("Provider: {$label} ({$class})");
        }
    }

    protected function resolveMaxPolls(int $timeout, int $poll): ?int
    {
        $configured = $this->option('max-polls');

        if ($configured !== null && $configured !== '') {
            return max(1, (int) $configured);
        }

        return (int) ceil($timeout / max(1, $poll));
    }
}
