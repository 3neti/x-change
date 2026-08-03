<?php

declare(strict_types=1);

use LBHurtado\XChange\Providers\XChangeServiceProvider;

it('publishes mobile PIN expectations for every affected Laravel starter auth test', function (): void {
    $provider = file_get_contents(
        (new ReflectionClass(XChangeServiceProvider::class))->getFileName(),
    );

    foreach ([
        'AuthenticationTest',
        'EmailVerificationTest',
        'PasswordResetTest',
        'RegistrationTest',
        'TwoFactorChallengeTest',
        'VerificationNotificationTest',
    ] as $test) {
        expect($provider)->toContain("stubs/tests/Feature/Auth/{$test}.php.stub");
    }
});

it('preserves Laravel passkey props in the x-change security scaffold', function (): void {
    $controller = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Http/Controllers/Settings/SecurityController.php.stub',
    );
    $securityTest = file_get_contents(
        dirname(__DIR__, 3).'/stubs/tests/Feature/Settings/SecurityTest.php.stub',
    );

    expect($controller)
        ->toContain("'canManagePasskeys' => Features::canManagePasskeys()")
        ->toContain('->passkeys()')
        ->toContain("'passwordRules' => Password::defaults()->toPasswordRulesString()")
        ->and($securityTest)
        ->toContain("->where('canManagePasskeys', Features::canManagePasskeys())");
});
