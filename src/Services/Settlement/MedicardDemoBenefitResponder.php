<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use DomainException;
use LBHurtado\XChange\Contracts\CampaignAutomaticDemonstrationResponderContract;
use LBHurtado\XChange\Data\Settlement\MedicardDemoBenefitResponseData;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionOutcomeData;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionPreparationData;

final class MedicardDemoBenefitResponder implements CampaignAutomaticDemonstrationResponderContract
{
    public function driverId(): string
    {
        return MedicardDemoBenefitPolicyCompletionDriver::DRIVER_ID;
    }

    public function driverVersion(): string
    {
        return MedicardDemoBenefitPolicyCompletionDriver::DRIVER_VERSION;
    }

    public function complete(
        PolicyCompletionPreparationData $preparation,
    ): PolicyCompletionOutcomeData {
        if ($preparation->driverId !== $this->driverId()
            || $preparation->driverVersion !== $this->driverVersion()) {
            throw new DomainException(
                'The Medicard demonstration responder requires the exact Medicard driver identity.',
            );
        }

        if ($preparation->paymentAmountMinor !== 5000
            || $preparation->currency !== 'PHP'
            || $preparation->privateApplicantEvidence() === []) {
            throw new DomainException(
                'The Medicard demonstration responder requires the locked PHP 50 payment and applicant evidence.',
            );
        }

        $reference = strtoupper(substr(hash(
            'sha256',
            'medicard-demo-benefit:'.$preparation->idempotencyKey.':'.$preparation->fingerprint,
        ), 0, 16));

        return (new MedicardDemoBenefitResponseData(
            demoReference: 'MEDICARD-DEMO-'.$reference,
            effectiveAt: $preparation->coverageEffectiveAt,
            expiresAt: $preparation->coverageExpiresAt,
        ))->outcome();
    }
}
