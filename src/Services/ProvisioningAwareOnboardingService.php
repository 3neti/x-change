<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Onboarding\Contracts\OnboardingServiceContract;
use LBHurtado\Onboarding\Data\OnboardingIntentData;
use LBHurtado\Onboarding\Data\OnboardingResultData;
use LBHurtado\Onboarding\Data\OnboardingStatusData;

class ProvisioningAwareOnboardingService implements OnboardingServiceContract
{
    public function __construct(
        protected OnboardingServiceContract $onboarding,
        protected StartProviderProvisioningFromOnboardingCompletion $completionHook,
    ) {}

    public function start(OnboardingIntentData $intent): OnboardingResultData
    {
        return $this->onboarding->start($intent);
    }

    public function status(string $reference): OnboardingStatusData
    {
        return $this->onboarding->status($reference);
    }

    public function complete(string $reference, array $payload = []): OnboardingResultData
    {
        $result = $this->onboarding->complete($reference, $payload);
        $provisioning = $this->completionHook->handle($result);

        if (! is_array($provisioning)) {
            return $result;
        }

        $meta = $result->meta;
        data_set($meta, 'xchange.provisioning', $provisioning);

        return new OnboardingResultData(
            reference: $result->reference,
            subject: $result->subject,
            requirements: $result->requirements,
            status: $result->status,
            flow: $result->flow,
            auth: $result->auth,
            result: $result->result,
            meta: $meta,
        );
    }

    public function cancel(string $reference, array $payload = []): OnboardingResultData
    {
        return $this->onboarding->cancel($reference, $payload);
    }

    public function handle(OnboardingIntentData $intent): OnboardingResultData
    {
        return $this->onboarding->handle($intent);
    }
}
