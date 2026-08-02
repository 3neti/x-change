<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Deployment\CloudDeploymentPlanner;
use LBHurtado\XChange\Services\Deployment\CloudInfrastructureApplier;
use LBHurtado\XChange\Services\Deployment\CloudStagingAcceptance;
use LBHurtado\XChange\Services\Deployment\DeploymentCheckpointRepository;
use LBHurtado\XChange\Services\Deployment\DeploymentManifestGenerator;
use LBHurtado\XChange\Services\Deployment\DeploymentManifestRepository;
use Throwable;

final class CloudRecipeCommand extends Command
{
    protected $signature = 'x-change:cloud
        {operation=plan : plan, apply, verify, ship, resume, or accept}
        {--environment=staging : Laravel Cloud environment}
        {--application= : Laravel Cloud application ID or name}
        {--profile= : Provider profile}
        {--path= : Deployment manifest path}
        {--confirm-production : Explicit production consent}
        {--confirm-apply : Permit the declared infrastructure changes}
        {--region=us-east-2 : Laravel Cloud region}
        {--database-preset=dev : Laravel Cloud database preset}
        {--database-type=postgres18 : Laravel Cloud database type}
        {--cache-type=redis : Laravel Cloud cache type}
        {--cache-size=flex-1 : Laravel Cloud cache size}
        {--compute-size=flex-1 : Laravel Cloud compute size}
        {--offline : Render desired state without reading Laravel Cloud}
        {--url= : Absolute application URL for staging acceptance}
        {--json : Render machine-readable output}';

    protected $description = 'Plan, apply, verify, ship, or resume the package-owned Cloud recipe.';

    public function handle(
        CloudDeploymentPlanner $planner,
        CloudInfrastructureApplier $applier,
        DeploymentManifestGenerator $generator,
        DeploymentManifestRepository $manifests,
        DeploymentCheckpointRepository $checkpoints,
        CloudStagingAcceptance $acceptance,
    ): int {
        $operation = trim((string) $this->argument('operation'));

        return match ($operation) {
            'plan' => $this->plan($planner, $generator, $manifests),
            'ship' => $this->ship($planner, $applier, $generator, $manifests),
            'verify' => $this->call('x-change:doctor', [
                '--strict' => true,
                '--no-interaction' => true,
            ]),
            'apply' => $this->apply($applier, $generator, $manifests),
            'resume' => $this->resume($planner, $generator, $manifests, $checkpoints),
            'accept' => $this->accept($acceptance),
            default => $this->invalidOperation($operation),
        };
    }

    private function ship(
        CloudDeploymentPlanner $planner,
        CloudInfrastructureApplier $applier,
        DeploymentManifestGenerator $generator,
        DeploymentManifestRepository $manifests,
    ): int {
        $path = trim((string) ($this->option('path') ?: base_path('x-change.deployment.yaml')));

        try {
            if (! is_file($path) || $this->option('profile') !== null) {
                $manifests->write($path, $generator->generate('laravel-cloud', $this->option('profile')));
            }

            $manifest = $manifests->read($path);
            $application = trim((string) ($this->option('application') ?: $manifest['application']['slug']));
            $environment = trim((string) $this->option('environment'));
            $plan = $planner->plan($manifest, $application, $environment);

            if ($plan['changes_required']) {
                if (! (bool) $this->option('confirm-apply')) {
                    $this->components->error('Cloud state differs from the recipe; review plan and rerun ship with --confirm-apply.');

                    return self::FAILURE;
                }

                $result = $applier->apply($manifest, $application, $environment, $this->applyOptions());

                if ($result['requires_replan']) {
                    $plan = $planner->plan($manifest, $application, $environment);

                    if ($plan['changes_required']) {
                        $this->components->error('Cloud changed successfully but still requires a fresh plan before deployment. Rerun ship.');

                        return self::FAILURE;
                    }
                }
            }
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        return $this->callDeploy(plan: false);
    }

    private function accept(CloudStagingAcceptance $acceptance): int
    {
        $url = trim((string) $this->option('url'));

        if ($url === '') {
            $this->components->error('Cloud acceptance requires --url.');

            return self::FAILURE;
        }

        try {
            $result = $acceptance->inspect($url);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($result['success']) {
            $this->components->info('Staging HTTP acceptance passed without provider or money movement.');
        } else {
            $this->components->error('Staging HTTP acceptance failed.');
        }

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }

    private function resume(
        CloudDeploymentPlanner $planner,
        DeploymentManifestGenerator $generator,
        DeploymentManifestRepository $manifests,
        DeploymentCheckpointRepository $checkpoints,
    ): int {
        $path = trim((string) ($this->option('path') ?: base_path('x-change.deployment.yaml')));

        try {
            if (! is_file($path)) {
                $manifests->write($path, $generator->generate('laravel-cloud', $this->option('profile')));
            }

            $manifest = $manifests->read($path);
            $application = trim((string) ($this->option('application') ?: $manifest['application']['slug']));
            $environment = trim((string) $this->option('environment'));
            $plan = $planner->plan($manifest, $application, $environment);
            $history = $checkpoints->read();
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($plan['changes_required']) {
            $this->components->error('Cloud state has drifted or is incomplete; run plan and apply before resuming deployment.');

            return self::FAILURE;
        }

        if ($history === []) {
            $this->components->error('No deployment checkpoint exists; use ship for the first deployment.');

            return self::FAILURE;
        }

        return $this->callDeploy(plan: false);
    }

    private function apply(
        CloudInfrastructureApplier $applier,
        DeploymentManifestGenerator $generator,
        DeploymentManifestRepository $manifests,
    ): int {
        if (! (bool) $this->option('confirm-apply')) {
            $this->components->error('Cloud infrastructure apply requires --confirm-apply.');

            return self::FAILURE;
        }

        $path = trim((string) ($this->option('path') ?: base_path('x-change.deployment.yaml')));

        try {
            if (! is_file($path) || $this->option('profile') !== null) {
                $manifests->write($path, $generator->generate('laravel-cloud', $this->option('profile')));
            }

            $manifest = $manifests->read($path);
            $result = $applier->apply(
                $manifest,
                trim((string) ($this->option('application') ?: $manifest['application']['slug'])),
                trim((string) $this->option('environment')),
                $this->applyOptions(),
            );
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info($result['status'] === 'no_changes'
                ? 'Laravel Cloud infrastructure already matches the recipe.'
                : 'Declared Laravel Cloud infrastructure changes were applied; re-plan before deployment.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{region: string, database_preset: string, database_type: string, cache_type: string, cache_size: string, compute_size: string}
     */
    private function applyOptions(): array
    {
        return [
            'region' => trim((string) $this->option('region')),
            'database_preset' => trim((string) $this->option('database-preset')),
            'database_type' => trim((string) $this->option('database-type')),
            'cache_type' => trim((string) $this->option('cache-type')),
            'cache_size' => trim((string) $this->option('cache-size')),
            'compute_size' => trim((string) $this->option('compute-size')),
        ];
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
