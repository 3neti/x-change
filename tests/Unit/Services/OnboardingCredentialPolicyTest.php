<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Onboarding\OnboardingCredentialPolicy;

it('requires OTP and invited Account PIN setup by default', function (): void {
    $policy = app(OnboardingCredentialPolicy::class);

    expect($policy->requiresMobileVerification())->toBeTrue()
        ->and($policy->requiresInvitedAccountPinSetup())->toBeTrue()
        ->and($policy->initialMobileVerifiedAt())->toBeNull();
});

it('supports an explicit frictionless local onboarding trial', function (): void {
    config()->set('x-change.onboarding.mobile_verification.enabled', false);
    config()->set('x-change.onboarding.voucher.require_pin_setup', false);
    $policy = app(OnboardingCredentialPolicy::class);

    expect($policy->requiresMobileVerification())->toBeFalse()
        ->and($policy->requiresInvitedAccountPinSetup())->toBeFalse()
        ->and($policy->initialMobileVerifiedAt())->not->toBeNull();
});
