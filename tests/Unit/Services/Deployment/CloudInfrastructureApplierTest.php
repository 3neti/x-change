<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use LBHurtado\XChange\Contracts\Deployment\CloudMutationGatewayContract;
use LBHurtado\XChange\Contracts\Deployment\CloudStateReaderContract;
use LBHurtado\XChange\Services\Deployment\CloudInfrastructureApplier;
use LBHurtado\XChange\Services\Deployment\CloudRecipeRepository;

function cloudApplyOptions(): array
{
    return [
        'region' => 'us-east-2',
        'database_preset' => 'dev',
        'database_type' => 'postgres18',
        'cache_type' => 'redis',
        'cache_size' => 'flex-1',
        'compute_size' => 'flex-1',
    ];
}

it('is a no-op when declared Cloud infrastructure already exists', function (): void {
    $state = new class implements CloudStateReaderContract
    {
        public function read(string $application, string $environment): array
        {
            $recipe = (new CloudRecipeRepository(new Filesystem))->read();

            return [
                'application' => ['exists' => true],
                'environment' => [
                    'exists' => true,
                    'id' => 'env-1',
                    'buildCommand' => implode("\n", $recipe['build']['commands']),
                    'deployCommand' => implode("\n", $recipe['deploy']['commands']),
                ],
                'resources' => [
                    'database' => ['attached' => true],
                    'cache' => ['attached' => true],
                    'compute' => ['attached' => true, 'instance_id' => 'instance-1'],
                    'websockets' => ['attached' => false],
                ],
                'runtime' => [
                    'queues' => ['x-change-funding', 'x-change-feedback', 'default'],
                    'scheduler' => true,
                ],
            ];
        }
    };
    $gateway = new class implements CloudMutationGatewayContract
    {
        public array $calls = [];

        public function bootstrap(string $application, string $region, string $databasePreset): void
        {
            $this->calls[] = __FUNCTION__;
        }

        public function createDatabase(string $environmentId, string $name, string $region, string $type): void
        {
            $this->calls[] = __FUNCTION__;
        }

        public function createCache(string $environmentId, string $name, string $region, string $type, string $size): void
        {
            $this->calls[] = __FUNCTION__;
        }

        public function createCompute(string $environmentId, string $name, string $size): string
        {
            $this->calls[] = __FUNCTION__;

            return 'instance';
        }

        public function configureEnvironment(string $environmentId, string $buildCommand, string $deployCommand): void
        {
            $this->calls[] = __FUNCTION__;
        }

        public function createWorker(string $instanceId, string $queue, int $timeout): void
        {
            $this->calls[] = __FUNCTION__;
        }

        public function enableScheduler(string $instanceId): void
        {
            $this->calls[] = __FUNCTION__;
        }
    };
    $applier = new CloudInfrastructureApplier($state, $gateway, new CloudRecipeRepository(new Filesystem));

    $result = $applier->apply(['runtime' => ['broadcasting_required' => false]], 'x-bank', 'staging', cloudApplyOptions());

    expect($result['status'])->toBe('no_changes')
        ->and($result['requires_replan'])->toBeFalse()
        ->and($gateway->calls)->toBe([]);
});

it('creates only missing workers and enables the scheduler once', function (): void {
    $state = new class implements CloudStateReaderContract
    {
        public function read(string $application, string $environment): array
        {
            return [
                'application' => ['exists' => true],
                'environment' => ['exists' => true, 'id' => 'env-1'],
                'resources' => [
                    'database' => ['attached' => true],
                    'cache' => ['attached' => true],
                    'compute' => ['attached' => true, 'instance_id' => 'instance-1'],
                    'websockets' => ['attached' => false],
                ],
                'runtime' => ['queues' => ['default'], 'scheduler' => false],
            ];
        }
    };
    $gateway = new class implements CloudMutationGatewayContract
    {
        public array $calls = [];

        public function bootstrap(string $application, string $region, string $databasePreset): void
        {
            $this->calls[] = __FUNCTION__;
        }

        public function createDatabase(string $environmentId, string $name, string $region, string $type): void
        {
            $this->calls[] = __FUNCTION__;
        }

        public function createCache(string $environmentId, string $name, string $region, string $type, string $size): void
        {
            $this->calls[] = __FUNCTION__;
        }

        public function createCompute(string $environmentId, string $name, string $size): string
        {
            $this->calls[] = __FUNCTION__;

            return 'instance';
        }

        public function configureEnvironment(string $environmentId, string $buildCommand, string $deployCommand): void
        {
            $this->calls[] = __FUNCTION__;
        }

        public function createWorker(string $instanceId, string $queue, int $timeout): void
        {
            $this->calls[] = __FUNCTION__.':'.$queue;
        }

        public function enableScheduler(string $instanceId): void
        {
            $this->calls[] = __FUNCTION__;
        }
    };
    $applier = new CloudInfrastructureApplier($state, $gateway, new CloudRecipeRepository(new Filesystem));

    $result = $applier->apply(['runtime' => ['broadcasting_required' => false]], 'x-bank', 'staging', cloudApplyOptions());

    expect($result['status'])->toBe('applied')
        ->and($gateway->calls)->toBe([
            'configureEnvironment',
            'createWorker:x-change-funding',
            'createWorker:x-change-feedback',
            'enableScheduler',
        ]);
});
