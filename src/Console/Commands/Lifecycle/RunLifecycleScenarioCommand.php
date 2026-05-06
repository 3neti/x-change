<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleResultRenderer;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleScenarioEngine;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleScenarioRepository;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleScenarioRunOptions;

class RunLifecycleScenarioCommand extends Command
{
    protected $signature = 'xchange:lifecycle:run
      {scenario? : Scenario key from x-change.lifecycle.scenarios}
        {--list : List available scenarios}
        {--provider=netbank : Provider label}
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

        $result = $engine->run(
            command: $this,
            scenarioKey: $scenarioKey,
            options: LifecycleScenarioRunOptions::fromConsoleOptions($this->options()),
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

        $payload = $this->pollDisbursement(
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

    protected function resolveMaxPolls(int $timeout, int $poll): ?int
    {
        $configured = $this->option('max-polls');

        if ($configured !== null && $configured !== '') {
            return max(1, (int) $configured);
        }

        return (int) ceil($timeout / max(1, $poll));
    }

    protected function pollDisbursement(
        string $code,
        int $timeout,
        int $poll,
        ?int $maxPolls = null,
        bool $acceptPending = false,
    ): array {
        $start = time();
        $attempt = 0;
        $last = [
            'voucher_code' => $code,
            'current_status' => 'unknown',
        ];

        do {
            $attempt++;

            Artisan::call('xchange:disbursement:check', [
                'code' => $code,
                '--sync' => true,
                '--json' => true,
            ]);

            $output = trim(Artisan::output());
            $decoded = json_decode($output, true);

            if (is_array($decoded)) {
                $last = $decoded;
                $status = $decoded['current_status'] ?? null;
                $providerTransactionId = $decoded['provider_transaction_id'] ?? null;
                $needsReview = (bool) ($decoded['needs_review'] ?? false);
                $provider = $decoded['provider'] ?? null;

                if (! $this->option('json')) {
                    $elapsed = time() - $start;
                    $maxPollsLabel = $maxPolls !== null ? (string) $maxPolls : '∞';

                    $this->line(sprintf(
                        '[poll %d/%s | %ss] status=%s provider_tx=%s needs_review=%s',
                        $attempt,
                        $maxPollsLabel,
                        $elapsed,
                        $status ?? 'unknown',
                        $providerTransactionId ?: 'n/a',
                        $needsReview ? 'yes' : 'no',
                    ));
                }

                if (in_array($status, ['succeeded', 'failed'], true)) {
                    return $decoded;
                }

                if (
                    $acceptPending
                    && $status === 'pending'
                    && ! $needsReview
                    && is_string($provider) && $provider !== ''
                    && is_string($providerTransactionId) && $providerTransactionId !== ''
                ) {
                    if (! $this->option('json')) {
                        $this->info('Trusted pending transaction accepted as good enough.');
                    }

                    return $decoded;
                }
            } elseif (! $this->option('json')) {
                $elapsed = time() - $start;
                $this->warn("[poll {$elapsed}s] unable to decode disbursement status response");
            }

            if ($maxPolls !== null && $attempt >= $maxPolls) {
                break;
            }

            if ((time() - $start) >= $timeout) {
                break;
            }

            sleep($poll);
        } while (true);

        $last['current_status'] = $last['current_status'] ?? 'timeout';
        $last['timed_out'] = true;
        $last['poll_attempts'] = $attempt;

        return $last;
    }
}
