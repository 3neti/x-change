<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use LBHurtado\XChange\Contracts\Deployment\CloudStateReaderContract;
use LBHurtado\XChange\Services\Deployment\DeploymentCheckpointRepository;

it('exposes the package Cloud plan through the umbrella command', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'xchange-cloud-');
    @unlink($path);

    try {
        $this->artisan('x-change:cloud', [
            'operation' => 'plan',
            '--environment' => 'staging',
            '--profile' => 'development',
            '--path' => $path,
            '--offline' => true,
            '--json' => true,
        ])->expectsOutputToContain('"status": "planned"')
            ->assertSuccessful();
    } finally {
        @unlink($path);
    }
});

it('runs safe staging HTTP acceptance through the Cloud entry point', function (): void {
    Http::fake(['https://x-bank.example/*' => Http::response('ready')]);

    $this->artisan('x-change:cloud', [
        'operation' => 'accept',
        '--url' => 'https://x-bank.example',
        '--json' => true,
    ])->expectsOutputToContain('"real_money_transfer": false')
        ->assertSuccessful();
});

it('rechecks live state before resuming from a sanitized checkpoint', function (): void {
    app()->instance(CloudStateReaderContract::class, new class implements CloudStateReaderContract
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
                    'queues' => ['x-change-funding', 'x-change-feedback', 'default'],
                    'scheduler' => true,
                ],
            ];
        }
    });
    Process::fake();
    $manifestPath = tempnam(sys_get_temp_dir(), 'xchange-resume-manifest-');
    $checkpointPath = tempnam(sys_get_temp_dir(), 'xchange-resume-checkpoint-');
    @unlink($manifestPath);
    @unlink($checkpointPath);
    config()->set('x-change.deployment.cloud_checkpoint_path', $checkpointPath);
    (new DeploymentCheckpointRepository(new Filesystem))->record(
        'staging', str_repeat('a', 64), str_repeat('b', 64), 'deploy', 'failed', $checkpointPath,
    );

    try {
        $this->artisan('x-change:cloud', [
            'operation' => 'resume',
            '--environment' => 'staging',
            '--application' => 'x-payout',
            '--profile' => 'development',
            '--path' => $manifestPath,
            '--confirm-production' => true,
            '--json' => true,
        ])->assertSuccessful();

        Process::assertRan(fn ($process): bool => $process->command === [
            'cloud', 'deploy', 'x-payout', 'staging', '-n',
        ]);
    } finally {
        @unlink($manifestPath);
        @unlink($checkpointPath);
    }
});

it('ships a converged Cloud environment through the single recipe command', function (): void {
    app()->instance(CloudStateReaderContract::class, new class implements CloudStateReaderContract
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
                    'queues' => ['x-change-funding', 'x-change-feedback', 'default'],
                    'scheduler' => true,
                ],
            ];
        }
    });
    Process::fake();
    $manifestPath = tempnam(sys_get_temp_dir(), 'xchange-ship-manifest-');
    $checkpointPath = tempnam(sys_get_temp_dir(), 'xchange-ship-checkpoint-');
    @unlink($manifestPath);
    @unlink($checkpointPath);
    config()->set('x-change.deployment.cloud_checkpoint_path', $checkpointPath);

    try {
        $this->artisan('x-change:cloud', [
            'operation' => 'ship',
            '--environment' => 'staging',
            '--application' => 'x-payout',
            '--profile' => 'development',
            '--path' => $manifestPath,
            '--confirm-production' => true,
            '--json' => true,
        ])->assertSuccessful();

        Process::assertRan(fn ($process): bool => $process->command === [
            'cloud', 'deploy', 'x-payout', 'staging', '-n',
        ]);
    } finally {
        @unlink($manifestPath);
        @unlink($checkpointPath);
    }
});

it('requires explicit confirmation before applying Cloud infrastructure', function (): void {
    $this->artisan('x-change:cloud', ['operation' => 'apply'])
        ->expectsOutputToContain('requires --confirm-apply')
        ->assertFailed();
});
