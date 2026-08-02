<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Deployment;

use Illuminate\Support\Arr;
use LBHurtado\XChange\Contracts\Deployment\CloudMutationGatewayContract;
use LBHurtado\XChange\Contracts\Deployment\CloudStateReaderContract;
use RuntimeException;

final readonly class CloudInfrastructureApplier
{
    public function __construct(
        private CloudStateReaderContract $state,
        private CloudMutationGatewayContract $cloud,
        private CloudRecipeRepository $recipes,
    ) {}

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array{region: string, database_preset: string, database_type: string, cache_type: string, cache_size: string, compute_size: string}  $options
     * @return array<string, mixed>
     */
    public function apply(array $manifest, string $application, string $environment, array $options): array
    {
        $actual = $this->state->read($application, $environment);

        if (! Arr::get($actual, 'application.exists', false)) {
            $this->cloud->bootstrap($application, $options['region'], $options['database_preset']);

            return $this->result(['application:bootstrapped'], true);
        }

        if (! Arr::get($actual, 'environment.exists', false)) {
            throw new RuntimeException('The application exists but the requested environment does not; create or select it, then re-plan.');
        }

        $environmentId = trim((string) Arr::get($actual, 'environment.id'));

        if ($environmentId === '') {
            throw new RuntimeException('Laravel Cloud environment identifier is unavailable.');
        }

        if (Arr::get($manifest, 'runtime.broadcasting_required', false)
            && ! Arr::get($actual, 'resources.websockets.attached', false)) {
            throw new RuntimeException('Broadcasting requires an explicitly provisioned WebSocket cluster before apply.');
        }

        $applied = [];
        $resourceName = $application.'-'.$environment;

        if (! Arr::get($actual, 'resources.database.attached', false)) {
            $this->cloud->createDatabase($environmentId, $resourceName, $options['region'], $options['database_type']);
            $applied[] = 'database:created-and-attached';
        }

        if (! Arr::get($actual, 'resources.cache.attached', false)) {
            $this->cloud->createCache(
                $environmentId, $resourceName, $options['region'], $options['cache_type'], $options['cache_size'],
            );
            $applied[] = 'cache:created-and-attached';
        }

        $instanceId = trim((string) Arr::get($actual, 'resources.compute.instance_id'));

        if (! Arr::get($actual, 'resources.compute.attached', false)) {
            $instanceId = $this->cloud->createCompute($environmentId, $resourceName, $options['compute_size']);
            $applied[] = 'compute:created';
        }

        if ($instanceId === '') {
            throw new RuntimeException('Laravel Cloud compute identifier is unavailable; re-plan before configuring workers.');
        }

        $recipe = $this->recipes->read();
        $buildCommand = implode("\n", (array) Arr::get($recipe, 'build.commands', []));
        $deployCommand = implode("\n", (array) Arr::get($recipe, 'deploy.commands', []));

        if (Arr::get($actual, 'environment.buildCommand') !== $buildCommand
            || Arr::get($actual, 'environment.deployCommand') !== $deployCommand) {
            $this->cloud->configureEnvironment($environmentId, $buildCommand, $deployCommand);
            $applied[] = 'environment:commands-configured';
        }

        $actualQueues = (array) Arr::get($actual, 'runtime.queues', []);
        $timeout = (int) Arr::get($recipe, 'resources.workers.timeout_seconds', 60);

        foreach ((array) Arr::get($recipe, 'resources.workers.queues', []) as $queue) {
            if (! in_array($queue, $actualQueues, true)) {
                $this->cloud->createWorker($instanceId, (string) $queue, $timeout);
                $applied[] = 'worker:'.$queue;
            }
        }

        if (Arr::get($actual, 'runtime.scheduler') !== true) {
            $this->cloud->enableScheduler($instanceId);
            $applied[] = 'scheduler:enabled';
        }

        return $this->result($applied, $applied !== []);
    }

    /** @param list<string> $applied
     * @return array<string, mixed>
     */
    private function result(array $applied, bool $requiresReplan): array
    {
        return [
            'schema' => 'x-change.cloud-apply.v1',
            'status' => $applied === [] ? 'no_changes' : 'applied',
            'applied' => $applied,
            'requires_replan' => $requiresReplan,
        ];
    }
}
