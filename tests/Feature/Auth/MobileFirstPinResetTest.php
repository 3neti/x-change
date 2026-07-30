<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Actions\Auth\ResetMobileFirstPin;
use LBHurtado\XChange\Services\Onboarding\AccountPinSetupState;
use LBHurtado\XChange\Tests\Fakes\User;

it('resets an established mobile-first Account PIN through the broker action', function (): void {
    $user = User::query()->create([
        'name' => 'Returning User',
        'email' => 'returning@example.test',
        'mobile' => '639171111111',
        'password' => Hash::make('1234'),
    ]);

    app(ResetMobileFirstPin::class)->reset($user, [
        'password' => '246810',
        'password_confirmation' => '246810',
    ]);

    expect(Hash::check(
        '246810',
        (string) $user->fresh()->getAuthPassword(),
    ))->toBeTrue();
});

it('lets PIN recovery complete a pending onboarding security setup', function (): void {
    $user = User::query()->create([
        'name' => 'Onboarded User',
        'email' => 'onboarded@example.test',
        'mobile' => '639172222222',
        'password' => Hash::make('unusable-random-credential'),
    ]);
    $pinSetup = app(AccountPinSetupState::class);
    $pinSetup->markRequired($user);

    app(ResetMobileFirstPin::class)->reset($user, [
        'password' => '135790',
        'password_confirmation' => '135790',
    ]);

    expect(Hash::check(
        '135790',
        (string) $user->fresh()->getAuthPassword(),
    ))->toBeTrue()
        ->and($pinSetup->isRequired($user->fresh()))->toBeFalse();
});

it('rejects non-numeric or unconfirmed recovery PINs', function (
    string $pin,
    string $confirmation,
): void {
    $user = User::query()->create([
        'name' => 'Protected User',
        'email' => 'protected@example.test',
        'mobile' => '639173333333',
        'password' => Hash::make('1234'),
    ]);

    expect(fn () => app(ResetMobileFirstPin::class)->reset($user, [
        'password' => $pin,
        'password_confirmation' => $confirmation,
    ]))->toThrow(ValidationException::class)
        ->and(Hash::check(
            '1234',
            (string) $user->fresh()->getAuthPassword(),
        ))->toBeTrue();
})->with([
    'letters' => ['abcd', 'abcd'],
    'mismatch' => ['246810', '135790'],
    'too short' => ['123', '123'],
]);
