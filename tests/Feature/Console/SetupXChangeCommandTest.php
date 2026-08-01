<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Console\Commands\SetupXChangeCommand;

it('renders the complete one-command setup plan without side effects', function (): void {
    $exitCode = Artisan::call('x-change:setup', [
        '--profile' => 'development',
        '--target' => 'local',
        '--name' => 'x-PayOut',
        '--url' => 'http://x-payout.test',
        '--dry-run' => true,
        '--json' => true,
    ]);
    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['schema'])->toBe('x-change.setup.v1')
        ->and($payload['write_local_environment'])->toBeFalse()
        ->and($payload['steps'])->toBe([
            'configure',
            'adopt_host',
            'preflight',
            'install',
            'frontend',
            'manifest',
            'verify',
        ]);
});

it('adopts the host before installation in the one-command workflow', function (): void {
    $source = file_get_contents(
        (new ReflectionClass(SetupXChangeCommand::class))->getFileName(),
    );

    expect($source)
        ->toContain('$hostUserModel->adopt()')
        ->and(strpos($source, '$hostUserModel->adopt()'))
        ->toBeLessThan(strpos($source, "'x-change:install'"))
        ->and($source)
        ->toContain('PHP_BINARY')
        ->toContain('Process::path(base_path())');
});

it('refreshes owned starter files only on first adoption or explicit force', function (): void {
    $source = file_get_contents(
        (new ReflectionClass(SetupXChangeCommand::class))->getFileName(),
    );

    expect($source)
        ->toContain("\$refreshOwnedScaffold = (bool) \$this->option('force')")
        ->toContain("|| \$adoption['changed']")
        ->toContain("...(\$refreshOwnedScaffold ? ['--force'] : [])");
});

it('keeps the local setup trial frictionless without changing production defaults', function (): void {
    $source = file_get_contents(
        (new ReflectionClass(SetupXChangeCommand::class))->getFileName(),
    );

    expect($source)
        ->toContain("'XCHANGE_MOBILE_VERIFICATION_ENABLED' => 'false'")
        ->toContain("'XCHANGE_ONBOARDING_REQUIRE_OTP' => 'false'")
        ->toContain("'XCHANGE_ONBOARDING_REQUIRE_PIN_SETUP' => 'false'");
});

it('requires explicit local environment writes outside interactive mode', function (): void {
    $this->artisan('x-change:setup', [
        '--profile' => 'development',
        '--target' => 'local',
        '--write-env' => true,
        '--dry-run' => true,
        '--json' => true,
    ])->expectsOutputToContain('"write_local_environment": true')
        ->assertSuccessful();
});

it('routes remote environments through the deployment command', function (): void {
    $this->artisan('x-change:setup', [
        '--target' => 'laravel-cloud',
        '--json' => true,
    ])->expectsOutputToContain('x-change:setup is local-only')
        ->assertFailed();
});
