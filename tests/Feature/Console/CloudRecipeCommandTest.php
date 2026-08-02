<?php

declare(strict_types=1);

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

it('requires explicit confirmation before applying Cloud infrastructure', function (): void {
    $this->artisan('x-change:cloud', ['operation' => 'apply'])
        ->expectsOutputToContain('requires --confirm-apply')
        ->assertFailed();
});
