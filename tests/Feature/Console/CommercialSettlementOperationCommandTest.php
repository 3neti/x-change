<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('registers explicit commercial settlement operation commands', function (): void {
    $commands = Artisan::all();

    expect($commands)->toHaveKeys([
        'x-change:commercial:provider-cost:record',
        'x-change:commercial:commission:request',
        'x-change:commercial:commission:approve',
        'x-change:commercial:commission:submit',
        'x-change:commercial:commission:reconcile',
    ]);
});

it('requires explicit live confirmation before commission submission', function (): void {
    expect(Artisan::call('x-change:commercial:commission:submit', [
        'reference' => 'missing-batch',
        'operator' => 'missing-operator',
        '--no-interaction' => true,
    ]))->toBe(1)
        ->and(Artisan::output())->toContain('--confirm-live');
});
