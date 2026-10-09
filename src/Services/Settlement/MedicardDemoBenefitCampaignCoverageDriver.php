<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use LBHurtado\XChange\Contracts\CampaignCoverageDriverContract;
use LBHurtado\XChange\Data\Settlement\CampaignCoverageDecisionData;
use LBHurtado\XChange\Data\Settlement\ProvisionalCoverageTermsData;
use LBHurtado\XChange\Models\CampaignPaymentRecognition;

final class MedicardDemoBenefitCampaignCoverageDriver implements CampaignCoverageDriverContract
{
    public const DRIVER_ID = MedicardDemoBenefitPolicyCompletionDriver::DRIVER_ID;

    public const DRIVER_VERSION = MedicardDemoBenefitPolicyCompletionDriver::DRIVER_VERSION;

    public function driverId(): string
    {
        return self::DRIVER_ID;
    }

    public function driverVersion(): string
    {
        return self::DRIVER_VERSION;
    }

    public function decide(CampaignPaymentRecognition $recognition): CampaignCoverageDecisionData
    {
        $run = (array) data_get($recognition->campaignRecord()->settings, 'scenario_run', []);

        if (data_get($run, 'scenario') !== 'medicard_demo_benefit'
            || data_get($run, 'envelope_driver_id') !== self::DRIVER_ID
            || data_get($run, 'envelope_driver_version') !== self::DRIVER_VERSION
            || data_get($run, 'product.code') !== 'MEDICARD_DEMO_DAY'
            || data_get($run, 'product.currency') !== 'PHP'
            || data_get($run, 'product.price_minor') !== 5000
            || $recognition->gross_amount_minor !== 5000
            || $recognition->currency !== 'PHP'
            || $recognition->provider_status !== 'settled'
            || ! $recognition->destination_verified
            || $recognition->settled_at === null) {
            return CampaignCoverageDecisionData::ineligible(
                'campaign_or_payment_not_qualified',
            );
        }

        return CampaignCoverageDecisionData::eligible(
            new ProvisionalCoverageTermsData(
                driverId: self::DRIVER_ID,
                driverVersion: self::DRIVER_VERSION,
                coverageType: 'healthcare-access-journey-demonstration',
                currency: 'PHP',
                effectiveAt: $recognition->settled_at,
                expiresAt: $recognition->settled_at->addDay(),
                coverageAmountMinor: null,
                terms: [
                    'product_code' => 'MEDICARD_DEMO_DAY',
                    'title' => 'MediCard Demo Benefit Pass',
                    'price_minor' => 5000,
                    'duration_hours' => 24,
                    'contract_authority' => 'demonstration_only',
                    'membership_created' => false,
                    'healthcare_coverage_created' => false,
                    'treatment_authorized' => false,
                ],
                authorization: [
                    'authority' => 'x-change-medicard-demonstration-driver',
                    'authority_reference' => (string) data_get($run, 'reference'),
                ],
            ),
            'recognized_demonstration_payment_qualified',
        );
    }
}
