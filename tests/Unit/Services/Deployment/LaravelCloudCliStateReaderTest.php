<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use LBHurtado\XChange\Services\Deployment\LaravelCloudCliStateReader;

it('reads only sanitized Cloud fields after checking each CLI command', function (): void {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'help'))
            ->push(Process::result(output: json_encode([
                ['id' => 'app-1', 'name' => 'x-Bank', 'slug' => 'x-bank'],
            ], JSON_THROW_ON_ERROR)))
            ->push(Process::result(output: 'help'))
            ->push(Process::result(output: json_encode([
                [
                    'id' => 'env-1',
                    'name' => 'staging',
                    'slug' => 'staging',
                    'status' => 'ready',
                    'instances' => [['id' => 'instance-1']],
                    'databaseSchemaId' => 'schema-1',
                    'cacheId' => 'cache-1',
                    'websocketApplicationId' => null,
                ],
            ], JSON_THROW_ON_ERROR)))
            ->push(Process::result(output: 'help'))
            ->push(Process::result(output: json_encode([
                ['queue' => 'x-change-funding,x-change-feedback,default'],
            ], JSON_THROW_ON_ERROR))),
    ]);

    $state = (new LaravelCloudCliStateReader)->read('x-bank', 'staging');

    expect($state['application']['exists'])->toBeTrue()
        ->and($state['environment']['exists'])->toBeTrue()
        ->and($state['resources']['database']['attached'])->toBeTrue()
        ->and($state['runtime']['queues'])->toBe([
            'x-change-funding', 'x-change-feedback', 'default',
        ]);

    Process::assertRan(static fn (PendingProcess $process): bool => $process->command === [
        'cloud', 'application:list', '-h', '-n',
    ]);
});
