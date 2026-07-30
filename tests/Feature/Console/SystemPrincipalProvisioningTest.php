<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use LBHurtado\XChange\Console\Commands\InstallXChangeCommand;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function (): void {
    config()->set('x-change.onboarding.issuer_model', User::class);
    config()->set('x-change.payout.system_user_column', 'email');
    config()->set('x-change.payout.system_user_id', 'system-alpha@example.test');
    config()->set('x-change.payout.system_wallet_slug', 'platform');
});

it('previews system-principal creation without mutating an Account', function () {
    $this->artisan('x-change:system-principal:provision', [
        '--json' => true,
    ])
        ->expectsOutputToContain('"status":"would_create"')
        ->assertExitCode(Command::SUCCESS);

    expect(User::query()->count())->toBe(0);
});

it('requires explicit commit controls', function (array $options, string $message) {
    $this->artisan('x-change:system-principal:provision', [
        '--json' => true,
        '--commit' => true,
        ...$options,
    ])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
})->with([
    'confirmation' => [
        [],
        '--confirm-system-principal',
    ],
    'authorization reference' => [
        ['--confirm-system-principal' => true],
        '--authorization-reference',
    ],
]);

it('provisions the non-interactive system principal and Account idempotently', function () {
    $arguments = [
        '--name' => 'Alpha System Principal',
        '--email' => 'system-alpha@example.test',
        '--authorization-reference' => 'deployment:alpha-001',
        '--commit' => true,
        '--confirm-system-principal' => true,
        '--json' => true,
    ];

    $this->artisan('x-change:system-principal:provision', $arguments)
        ->expectsOutputToContain('"status":"provisioned"')
        ->assertSuccessful();

    $principal = User::query()->sole();
    $password = (string) $principal->password;

    $this->artisan('x-change:system-principal:provision', $arguments)
        ->expectsOutputToContain('"status":"existing_ready"')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(1)
        ->and($principal->wallet()->where('slug', 'platform')->count())->toBe(1)
        ->and($principal->fresh()->password)->toBe($password)
        ->and(Hash::check('password', $password))->toBeFalse()
        ->and(data_get(
            $principal->fresh()->onboarding_meta,
            'system_principal.authorization_reference',
        ))->toBe('deployment:alpha-001')
        ->and(data_get(
            $principal->fresh()->onboarding_meta,
            'system_principal.interactive_login',
        ))->toBeFalse();
});

it('rejects a conflicting authorization reference on retry', function () {
    $base = [
        '--authorization-reference' => 'deployment:alpha-001',
        '--commit' => true,
        '--confirm-system-principal' => true,
        '--json' => true,
    ];

    $this->artisan('x-change:system-principal:provision', $base)
        ->assertSuccessful();

    $this->artisan('x-change:system-principal:provision', [
        ...$base,
        '--authorization-reference' => 'deployment:other',
    ])
        ->expectsOutputToContain('different authorization reference')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});

it('requires installer system-principal controls to travel together', function () {
    $signature = (new ReflectionClass(InstallXChangeCommand::class))
        ->getDefaultProperties()['signature'];

    expect($signature)
        ->toContain('{--provision-system-principal')
        ->toContain('{--system-principal-authorization-reference=')
        ->toContain('{--confirm-system-principal');

    $this->artisan('x-change:install', [
        '--no-treasury' => true,
        '--no-migrate' => true,
        '--system-principal-email' => 'system-alpha@example.test',
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain(
            'System-principal options require [--provision-system-principal].',
        )
        ->assertFailed();
});
