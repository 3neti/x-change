<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Exceptions\FundingProviderUnavailable;
use LBHurtado\XChange\Models\FundingIntent;

final readonly class ManualFundingVerificationEligibility
{
    public function __construct(private FundingProviderAdapterRegistry $providers) {}

    public function allows(FundingIntent $intent): bool
    {
        $provider = $intent->provider_code;
        if (! is_string($provider) || preg_match('/\A[a-z0-9_]+\z/', $provider) !== 1
            || ! (bool) config("x-change.funding.providers.{$provider}.enabled", false)
            || $intent->status !== FundingIntentStatus::AwaitingFunds
            || $intent->expires_at?->isPast() !== false) {
            return false;
        }

        try {
            $this->providers->for($provider);
        } catch (FundingProviderUnavailable) {
            return false;
        }

        return true;
    }
}
