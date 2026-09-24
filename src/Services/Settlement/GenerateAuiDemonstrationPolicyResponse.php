<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use DomainException;
use LBHurtado\XChange\Data\Settlement\AuiDemonstrationPolicyResponseData;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionPreparationData;

final class GenerateAuiDemonstrationPolicyResponse
{
    public function handle(
        PolicyCompletionPreparationData $preparation,
    ): AuiDemonstrationPolicyResponseData {
        if ($preparation->driverId !== AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID
            || $preparation->driverVersion !== AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION) {
            throw new DomainException('The demonstration responder only accepts the reserved AUI driver.');
        }

        if ($preparation->privateApplicantEvidence() === []) {
            throw new DomainException('The demonstration responder requires completed applicant evidence.');
        }

        $reference = strtoupper(substr(hash(
            'sha256',
            'aui-demo-policy:'.$preparation->idempotencyKey.':'.$preparation->fingerprint,
        ), 0, 16));

        return new AuiDemonstrationPolicyResponseData(
            policyReference: 'AUI-DEMO-'.$reference,
            productCode: 'AUI-PA-DEMO',
            effectiveAt: $preparation->coverageEffectiveAt,
            expiresAt: $preparation->coverageExpiresAt,
        );
    }
}
