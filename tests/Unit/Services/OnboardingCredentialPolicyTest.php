<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Onboarding\OnboardingCredentialPolicy;

it('defaults onboarding OTP off while retaining invited Account PIN setup', function (): void {
    $policy = app(OnboardingCredentialPolicy::class);

    expect($policy->requiresMobileVerification())->toBeFalse()
        ->and($policy->requiresInvitedAccountPinSetup())->toBeTrue()
        ->and($policy->initialMobileVerifiedAt())->not->toBeNull();
});

it('supports an explicit frictionless local onboarding trial', function (): void {
    config()->set('x-change.onboarding.mobile_verification.enabled', false);
    config()->set('x-change.onboarding.voucher.require_pin_setup', false);
    $policy = app(OnboardingCredentialPolicy::class);

    expect($policy->requiresMobileVerification())->toBeFalse()
        ->and($policy->requiresInvitedAccountPinSetup())->toBeFalse()
        ->and($policy->initialMobileVerifiedAt())->not->toBeNull();
});
