<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Deployment\CloudDeploymentPlanner;
use LBHurtado\XChange\Services\Deployment\DeploymentManifestGenerator;
use LBHurtado\XChange\Services\Deployment\DeploymentManifestRepository;
use Throwable;

final class CloudRecipeCommand extends Command
{
    protected $signature = 'x-change:cloud
        {operation=plan : plan, apply, verify, ship, or resume}
        {--environment=staging : Laravel Cloud environment}
        {--application= : Laravel Cloud application ID or name}
        {--profile= : Provider profile}
        {--path= : Deployment manifest path}
        {--confirm-production : Explicit production consent}
        {--offline : Render desired state without reading Laravel Cloud}
        {--json : Render machine-readable output}';

    protected $description = 'Plan, apply, verify, ship, or resume the package-owned Cloud recipe.';

    public function handle(
        CloudDeploymentPlanner $planner,
        DeploymentManifestGenerator $generator,
        DeploymentManifestRepository $manifests,
    ): int {
        $operation = trim((string) $this->argument('operation'));

        return match ($operation) {
            'plan' => $this->plan($planner, $generator, $manifests),
            'ship' => $this->callDeploy(plan: false),
            'verify' => $this->call('x-change:doctor', [
                '--strict' => true,
                '--no-interaction' => true,
            ]),
            'apply', 'resume' => $this->notAvailableYet($operation),
            default => $this->invalidOperation($operation),
        };
    }

    private function plan(
        CloudDeploymentPlanner $planner,
        DeploymentManifestGenerator $generator,
        DeploymentManifestRepository $manifests,
    ): int {
        $path = trim((string) ($this->option('path') ?: base_path('x-change.deployment.yaml')));

        try {
            if (! is_file($path) || $this->option('profile') !== null) {
                $manifests->write($path, $generator->generate(
                    target: 'laravel-cloud',
                    profileName: $this->option('profile'),
                ));
            }

            $manifest = $manifests->read($path);
            $plan = $planner->plan(
                manifest: $manifest,
                application: trim((string) ($this->option('application') ?: $manifest['application']['slug'])),
                environment: trim((string) $this->option('environment')),
                offline: (bool) $this->option('offline'),
            );
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $plan['status'] = 'planned';

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($plan, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info('Laravel Cloud desired-state plan is ready; no changes were made.');
            $this->table(['Resource', 'Status'], collect($plan['operations'])
                ->map(static fn (array $operation): array => [
                    $operation['resource'],
                    $operation['status'],
                ])->all());
        }

        return self::SUCCESS;
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
