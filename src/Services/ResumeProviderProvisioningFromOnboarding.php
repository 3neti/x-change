<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\ProviderProvisioningManagerContract;
use LBHurtado\XChange\Contracts\XChangeOnboardingGatewayContract;

class ResumeProviderProvisioningFromOnboarding
{
    public function __construct(
        protected XChangeOnboardingGatewayContract $onboarding,
        protected ProviderProvisioningManagerContract $provisioning,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function handle(?string $reference, mixed $owner, array $payload): ?array
    {
        if (! is_string($reference) || trim($reference) === '') {
            return null;
        }

        $onboarding = $this->onboarding->ensureReady($reference);

        if ((bool) data_get($onboarding, 'ready') !== true || ! is_object($owner)) {
            return [
                'reference' => $reference,
                'ready' => false,
                'onboarding' => $onboarding,
                'provisioning' => null,
            ];
        }

        $provisioning = $this->provisioning->startOrResume($owner, $payload);

        return [
            'reference' => $reference,
            'ready' => (bool) data_get($provisioning, 'ready', false),
            'onboarding' => $onboarding,
            'provisioning' => $provisioning,
        ];
    }
}
