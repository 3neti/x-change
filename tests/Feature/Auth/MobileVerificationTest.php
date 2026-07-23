<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use LBHurtado\XChange\Actions\Auth\CreateNewMobileFirstUser;
use LBHurtado\XChange\Actions\Auth\StartMobileVerification;
use LBHurtado\XChange\Actions\Auth\VerifyMobileVerification;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\MobileVerificationChallenge;
use LBHurtado\XChange\Tests\Fakes\User;

it('creates a mobile-first user as unverified', function () {
    if (! interface_exists(CreatesNewUsers::class)) {
        expect(file_get_contents(__DIR__.'/../../../src/Actions/Auth/CreateNewMobileFirstUser.php'))
            ->toContain("'mobile_verified_at' => null");

        return;
    }

    config()->set('auth.providers.users.model', User::class);

    $user = app(CreateNewMobileFirstUser::class)->create([
        'name' => 'New Mobile User',
        'mobile' => '0917 301 1987',
        'email' => null,
        'password' => '1234',
        'password_confirmation' => '1234',
    ]);

    expect($user->getRawOriginal('mobile'))->toBe('639173011987')
        ->and($user->getAttribute('mobile_verified_at'))->toBeNull();
});

it('stores only a hashed mobile when requesting an onboarding code', function () {
    $user = actingAsTestUser();
    $user->forceFill([
        'mobile' => '639173011987',
        'mobile_verified_at' => null,
    ])->save();

    $challenge = app(StartMobileVerification::class)->handle($user);

    expect($challenge->status)->toBe('pending')
        ->and($challenge->provider)->toBe('null')
        ->and($challenge->mobile_hash)->toHaveLength(64)
        ->and($challenge->mobile_hash)->not->toBe('639173011987')
        ->and(json_encode($challenge->getAttributes()))->not->toContain('639173011987');
});

it('verifies the mobile only after the OTP provider confirms the code', function () {
    $user = actingAsTestUser();
    $user->forceFill([
        'mobile' => '639173011987',
        'mobile_verified_at' => null,
    ])->save();
    $challenge = app(StartMobileVerification::class)->handle($user);

    expect(fn () => app(VerifyMobileVerification::class)->handle($user, '123456'))
        ->toThrow(ValidationException::class, 'verification code is invalid');

    $verified = app(VerifyMobileVerification::class)->handle($user, '000000');

    expect($verified->status)->toBe('verified')
        ->and($verified->attempts)->toBe(1)
        ->and($verified->verified_at)->not->toBeNull()
        ->and($user->refresh()->getAttribute('mobile_verified_at'))->not->toBeNull();
});

it('refuses the null OTP driver outside explicitly allowed environments', function () {
    config()->set('x-change.onboarding.mobile_verification.allow_null_driver_environments', []);
    $user = actingAsTestUser();
    $user->forceFill([
        'mobile' => '639173011987',
        'mobile_verified_at' => null,
    ])->save();

    expect(fn () => app(StartMobileVerification::class)->handle($user))
        ->toThrow(ValidationException::class, 'delivery is not configured');

    expect(MobileVerificationChallenge::query()->count())->toBe(0);
});

it('renders the mobile verification page without exposing the full mobile', function () {
    $user = actingAsTestUser();
    $user->forceFill([
        'mobile' => '639173011987',
        'mobile_verified_at' => null,
    ])->save();

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.onboarding.mobile-verification.show'));

    $response->assertOk()
        ->assertJsonPath('component', 'x-change/onboarding/MobileVerification')
        ->assertJsonPath('props.mobile', '63••••••1987')
        ->assertJsonPath('props.verified', false)
        ->assertJsonPath('props.local_code', '000000')
        ->assertJsonMissing(['639173011987']);
});

it('blocks QR Ph simulation before mobile verification and creates no Funding Intent', function () {
    $user = actingAsTestUser();
    $user->forceFill([
        'email_verified_at' => now(),
        'mobile' => '639173011987',
        'mobile_verified_at' => null,
    ])->save();

    $this->postJson(route(
        'x-change.cockpit.funding.scenarios.qrph.store',
    ))->assertForbidden()
        ->assertJsonPath('message', 'Verify your mobile number before continuing.');

    expect(FundingIntent::query()->count())->toBe(0);
});
