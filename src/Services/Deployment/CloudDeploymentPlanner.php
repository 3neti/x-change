<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Deployment;

use Illuminate\Support\Arr;
use LBHurtado\XChange\Contracts\Deployment\CloudStateReaderContract;

final readonly class CloudDeploymentPlanner
{
    public function __construct(
        private CloudStateReaderContract $state,
        private CloudRecipeRepository $recipes,
    ) {}

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    public function plan(array $manifest, string $application, string $environment, bool $offline = false): array
    {
        $recipe = $this->recipes->read();
        $actual = $offline
            ? $this->offlineState($application, $environment)
            : $this->state->read($application, $environment);
        $requiredQueues = array_values((array) Arr::get($recipe, 'resources.workers.queues', []));
        $actualQueues = array_values((array) Arr::get($actual, 'runtime.queues', []));
        $operations = [
            $this->operation('application', (bool) Arr::get($actual, 'application.exists', false)),
            $this->operation('environment', (bool) Arr::get($actual, 'environment.exists', false)),
            $this->operation('postgresql', (bool) Arr::get($actual, 'resources.database.attached', false)),
            $this->operation('shared-cache', (bool) Arr::get($actual, 'resources.cache.attached', false)),
            $this->operation('compute', (bool) Arr::get($actual, 'resources.compute.attached', false)),
            [
                'resource' => 'workers',
                'status' => $offline
                    ? 'unknown'
                    : (array_diff($requiredQueues, $actualQueues) === [] ? 'ready' : 'change_required'),
                'required_queues' => $requiredQueues,
                'missing_queues' => $offline ? $requiredQueues : array_values(array_diff($requiredQueues, $actualQueues)),
            ],
            [
                'resource' => 'scheduler',
                'status' => Arr::get($actual, 'runtime.scheduler') === true ? 'ready' : 'verification_required',
            ],
        ];

        if ((bool) Arr::get($manifest, 'runtime.broadcasting_required', false)) {
            $operations[] = $this->operation(
                'websockets',
                (bool) Arr::get($actual, 'resources.websockets.attached', false),
            );
        }

        return [
            'schema' => 'x-change.cloud-plan.v1',
            'application' => $application,
            'environment' => $environment,
            'profile' => Arr::get($manifest, 'deployment.profile'),
            'recipe_hash' => Arr::get($manifest, 'recipe.hash'),
            'manifest_hash' => Arr::get($manifest, 'manifest_hash'),
            'offline' => $offline,
            'operations' => $operations,
            'changes_required' => collect($operations)->contains(
                static fn (array $operation): bool => in_array(
                    $operation['status'],
                    ['create', 'change_required'],
                    true,
                ),
            ),
        ];
    }

    /** @return array{resource: string, status: string} */
    private function operation(string $resource, bool $ready): array
    {
        return ['resource' => $resource, 'status' => $ready ? 'ready' : 'create'];
    }

    /** @return array<string, mixed> */
    private function offlineState(string $application, string $environment): array
    {
        return [
            'application' => ['exists' => false, 'requested' => $application],
            'environment' => ['exists' => false, 'requested' => $environment],
            'resources' => [],
            'runtime' => ['queues' => [], 'scheduler' => 'unknown'],
        ];
    }
}
