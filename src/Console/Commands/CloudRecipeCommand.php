<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;

final class CloudRecipeCommand extends Command
{
    protected $signature = 'x-change:cloud
        {operation=plan : plan, apply, verify, ship, or resume}
        {--environment=staging : Laravel Cloud environment}
        {--application= : Laravel Cloud application ID or name}
        {--profile= : Provider profile}
        {--path= : Deployment manifest path}
        {--confirm-production : Explicit production consent}
        {--json : Render machine-readable output}';

    protected $description = 'Plan, apply, verify, ship, or resume the package-owned Cloud recipe.';

    public function handle(): int
    {
        $operation = trim((string) $this->argument('operation'));

        return match ($operation) {
            'plan' => $this->callDeploy(plan: true),
            'ship' => $this->callDeploy(plan: false),
            'verify' => $this->call('x-change:doctor', [
                '--strict' => true,
                '--no-interaction' => true,
            ]),
            'apply', 'resume' => $this->notAvailableYet($operation),
            default => $this->invalidOperation($operation),
        };
    }

    private function callDeploy(bool $plan): int
    {
        return $this->call('x-change:deploy', array_filter([
            'environment' => trim((string) $this->option('environment')),
            '--application' => $this->option('application'),
            '--profile' => $this->option('profile'),
            '--path' => $this->option('path'),
            '--confirm-production' => (bool) $this->option('confirm-production'),
            '--plan' => $plan,
            '--json' => (bool) $this->option('json'),
        ], static fn (mixed $value): bool => $value !== null));
    }

    private function notAvailableYet(string $operation): int
    {
        $this->components->error("Cloud operation [{$operation}] is not enabled until its idempotent adapter is installed.");

        return self::FAILURE;
    }

    private function invalidOperation(string $operation): int
    {
        $this->components->error("Unknown Cloud operation [{$operation}].");

        return self::INVALID;
    }
}
