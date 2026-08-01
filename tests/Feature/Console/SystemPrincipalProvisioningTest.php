<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
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

it('requires explicit confirmation before committing', function (): void {
    $this->artisan('x-change:system-principal:provision', [
        '--json' => true,
        '--commit' => true,
    ])
        ->expectsOutputToContain('--confirm-system-principal')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('generates and reuses a stable provisioning reference automatically', function (): void {
    $arguments = [
        '--commit' => true,
        '--confirm-system-principal' => true,
        '--json' => true,
    ];

    $this->artisan('x-change:system-principal:provision', $arguments)
        ->assertSuccessful();

    $principal = User::query()->sole();
    $reference = (string) data_get(
        $principal->onboarding_meta,
        'system_principal.authorization_reference',
    );

    config()->set('app.key', 'base64:BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB=');

    $this->artisan('x-change:system-principal:provision', $arguments)
        ->assertSuccessful();

    expect($reference)->toStartWith('system-principal:auto:v1:')
        ->and(data_get(
            $principal->fresh()->onboarding_meta,
            'system_principal.authorization_reference',
        ))->toBe($reference);
});

it('fails before mutation when an automatic reference has no stable application key', function (): void {
    config()->set('app.key');

    $this->artisan('x-change:system-principal:provision', [
        '--commit' => true,
        '--confirm-system-principal' => true,
        '--json' => true,
    ])
        ->expectsOutputToContain('stable application key')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

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

it('allows the installer to provision the sole fresh-database Account without a seeder', function () {
    config()->set('x-change.deployment.profile', 'development');
    config()->set('x-change.treasury.connections', []);
    config()->set('x-change.treasury.legal_entity_reference', 'legal-entity:test');
    config()->set('x-change.treasury.legal_profile', 'treasury-settlement-ph-v1');
    config()->set('x-change.treasury.legal_profile_version', '2026-07-24.1');
    app('migrator')->path(Orchestra\Testbench\default_migration_path());

    $this->artisan('x-change:install', [
        '--fresh-database' => true,
        '--confirm-database-reset' => true,
        '--provision-system-principal' => true,
        '--system-principal-name' => 'Alpha System Principal',
        '--system-principal-email' => 'system-alpha@example.test',
        '--confirm-system-principal' => true,
        '--no-auth' => true,
        '--no-auth-tests' => true,
        '--no-settings' => true,
        '--no-settings-tests' => true,
        '--no-assets' => true,
        '--no-handlers' => true,
        '--no-rider' => true,
        '--no-x-ray' => true,
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('X-Change installed successfully.')
        ->assertSuccessful();

    $principal = User::query()->sole();

    expect($principal->email)->toBe('system-alpha@example.test')
        ->and($principal->wallet()->where('slug', 'platform')->count())->toBe(1)
        ->and(data_get(
            $principal->onboarding_meta,
            'system_principal.interactive_login',
        ))->toBeFalse();
});

it('rejects an unstable system-principal identifier before a fresh database reset', function () {
    config()->set('x-change.payout.system_user_column', 'id');
    config()->set('x-change.payout.system_user_id', '1');
    $commands = [];
    Event::listen(
        CommandStarting::class,
        function (CommandStarting $event) use (&$commands): void {
            $commands[] = $event->command;
        },
    );

    $this->artisan('x-change:install', [
        '--fresh-database' => true,
        '--confirm-database-reset' => true,
        '--provision-system-principal' => true,
        '--confirm-system-principal' => true,
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('XCHANGE_SYSTEM_USER_COLUMN=email')
        ->assertFailed();

    expect($commands)->not->toContain('migrate:fresh');
});
