<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Onboarding;

use Carbon\CarbonImmutable;

final class OnboardingCredentialPolicy
{
    public function requiresMobileVerification(): bool
    {
        return (bool) config(
            'x-change.onboarding.mobile_verification.enabled',
            false,
        );
    }

    public function requiresInvitedAccountPinSetup(): bool
    {
        return (bool) config(
            'x-change.onboarding.voucher.require_pin_setup',
            true,
        );
    }

    public function initialMobileVerifiedAt(): ?CarbonImmutable
    {
        return $this->requiresMobileVerification() ? null : now()->toImmutable();
    }
}
