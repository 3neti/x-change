<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use LBHurtado\XChange\Services\Deployment\DeploymentCheckpointRepository;

it('persists append-only sanitized deployment checkpoints', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'xchange-checkpoint-');
    @unlink($path);
    $repository = new DeploymentCheckpointRepository(new Filesystem);

    try {
        $repository->record('staging', str_repeat('a', 64), str_repeat('b', 64), 'deploy', 'succeeded', $path);
        $repository->record('staging', str_repeat('a', 64), str_repeat('b', 64), 'commission', 'succeeded', $path);

        $records = $repository->read($path);
        $contents = file_get_contents($path);

        expect($records)->toHaveCount(2)
            ->and($records[0]['operation'])->toBe('deploy')
            ->and($records[1]['operation'])->toBe('commission')
            ->and($contents)->not->toContain('password', 'token', 'secret');
    } finally {
        @unlink($path);
    }
});
