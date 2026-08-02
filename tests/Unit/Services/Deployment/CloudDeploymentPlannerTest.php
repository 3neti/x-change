<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use LBHurtado\XChange\Contracts\Deployment\CloudStateReaderContract;
use LBHurtado\XChange\Services\Deployment\CloudDeploymentPlanner;
use LBHurtado\XChange\Services\Deployment\CloudRecipeRepository;

it('renders a stable sanitized no-op plan for ready Cloud state', function (): void {
    $state = new class implements CloudStateReaderContract
    {
        public function read(string $application, string $environment): array
        {
            return [
                'application' => ['exists' => true],
                'environment' => ['exists' => true],
                'resources' => [
                    'database' => ['attached' => true],
                    'cache' => ['attached' => true],
                    'compute' => ['attached' => true],
                    'websockets' => ['attached' => false],
                ],
                'runtime' => [
                    'queues' => ['default', 'x-change-feedback', 'x-change-funding'],
                    'scheduler' => true,
                ],
            ];
        }
    };
    $planner = new CloudDeploymentPlanner(
        $state,
        new CloudRecipeRepository(new Filesystem),
    );
    $manifest = [
        'deployment' => ['profile' => 'netbank'],
        'recipe' => ['hash' => str_repeat('a', 64)],
        'manifest_hash' => str_repeat('b', 64),
        'runtime' => ['broadcasting_required' => false],
    ];

    $first = $planner->plan($manifest, 'x-bank', 'staging');
    $second = $planner->plan($manifest, 'x-bank', 'staging');
    $encoded = json_encode($first, JSON_THROW_ON_ERROR);

    expect($first)->toBe($second)
        ->and($first['changes_required'])->toBeFalse()
        ->and(collect($first['operations'])->pluck('status')->all())
        ->each->toBe('ready')
        ->and($encoded)->not->toContain('secret', 'token', 'password');
});

it('marks missing resources and queues without inventing scheduler state', function (): void {
    $state = new class implements CloudStateReaderContract
    {
        public function read(string $application, string $environment): array
        {
            return [
                'application' => ['exists' => true],
                'environment' => ['exists' => false],
                'resources' => [],
                'runtime' => ['queues' => ['default'], 'scheduler' => 'unknown'],
            ];
        }
    };
    $planner = new CloudDeploymentPlanner(
        $state,
        new CloudRecipeRepository(new Filesystem),
    );
    $plan = $planner->plan([
        'deployment' => ['profile' => 'netbank'],
        'runtime' => ['broadcasting_required' => true],
    ], 'x-bank', 'staging');

    expect($plan['changes_required'])->toBeTrue()
        ->and(collect($plan['operations'])->firstWhere('resource', 'workers')['missing_queues'])
        ->toBe(['x-change-funding', 'x-change-feedback'])
        ->and(collect($plan['operations'])->firstWhere('resource', 'scheduler')['status'])
        ->toBe('verification_required')
        ->and(collect($plan['operations'])->firstWhere('resource', 'websockets')['status'])
        ->toBe('create');
});
