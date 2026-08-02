<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use LBHurtado\XChange\Services\Deployment\HostComposerScriptAdopter;

it('idempotently adds explicit Cloud aliases to the host root package', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'xchange-composer-');
    file_put_contents($path, json_encode([
        'name' => 'bank/x-payout',
        'scripts' => ['test' => 'php artisan test'],
    ], JSON_THROW_ON_ERROR));
    $adopter = new HostComposerScriptAdopter(new Filesystem);

    try {
        expect($adopter->adopt($path, false)['status'])->toBe('would_adopt')
            ->and($adopter->adopt($path, true)['status'])->toBe('adopted')
            ->and($adopter->adopt($path, true)['status'])->toBe('already_adopted');

        $composer = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        expect($composer['scripts']['test'])->toBe('php artisan test')
            ->and($composer['scripts']['x-change:cloud:plan'])->toBe('@php vendor/bin/x-change-cloud plan')
            ->and($composer['scripts']['x-change:cloud:ship'])->toBe('@php vendor/bin/x-change-cloud ship')
            ->and($composer['scripts']['x-change:cloud:verify'])->toBe('@php vendor/bin/x-change-cloud verify');
    } finally {
        @unlink($path);
    }
});

it('refuses to replace a host-owned Composer alias', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'xchange-composer-');
    file_put_contents($path, json_encode([
        'scripts' => ['x-change:cloud:ship' => 'custom command'],
    ], JSON_THROW_ON_ERROR));
    $adopter = new HostComposerScriptAdopter(new Filesystem);

    try {
        expect(fn () => $adopter->adopt($path, true))
            ->toThrow(RuntimeException::class, 'already has a different command');
    } finally {
        @unlink($path);
    }
});
