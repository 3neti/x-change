<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use LBHurtado\XChange\Contracts\CampaignCoverageDriverContract;
use LBHurtado\XChange\Data\Settlement\CampaignCoverageDecisionData;
use LBHurtado\XChange\Data\Settlement\ProvisionalCoverageTermsData;
use LBHurtado\XChange\Models\CampaignPaymentRecognition;

final class AuiPersonalAccidentCampaignCoverageDriver implements CampaignCoverageDriverContract
{
    public const DRIVER_ID = 'aui.personal-accident.provisional-cover';

    public const DRIVER_VERSION = '1.0.0';

    public function __construct(private CampaignWorkflowPublicationResolver $publications) {}

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
        $campaign = $recognition->campaignRecord();
        $publication = $this->publications->forCampaignRevision($campaign, (string) $recognition->campaign_revision_id);
        if ($publication !== null) {
            return $this->decidePublished($recognition, $publication);
        }
        $run = (array) data_get($campaign->settings, 'scenario_run', []);
        $premiumMinor = data_get($run, 'product.premium_minor');
        $insuredAmountMinor = data_get($run, 'product.insured_amount_minor');
        $coverageDurationHours = data_get($run, 'product.coverage_duration_hours');
        $productCurrency = data_get($run, 'product.currency');
        $productName = data_get($run, 'product.name');

        if (data_get($run, 'scenario') !== 'aui_on_demand_insurance_payment'
            || data_get($run, 'envelope_driver_id') !== self::DRIVER_ID
            || data_get($run, 'envelope_driver_version') !== self::DRIVER_VERSION
            || ! is_int($premiumMinor)
            || ! is_int($insuredAmountMinor)
            || ! is_int($coverageDurationHours)
            || $premiumMinor <= 0
            || $insuredAmountMinor <= 0
            || $coverageDurationHours <= 0
            || $recognition->gross_amount_minor !== $premiumMinor
            || $recognition->currency !== $productCurrency
            || ! is_string($productName)
            || trim($productName) === ''
            || $recognition->provider_status !== 'settled'
            || ! $recognition->destination_verified
            || $recognition->settled_at === null) {
            return CampaignCoverageDecisionData::ineligible('campaign_or_payment_not_qualified');
        }

        return CampaignCoverageDecisionData::eligible(
            new ProvisionalCoverageTermsData(
                driverId: self::DRIVER_ID,
                driverVersion: self::DRIVER_VERSION,
                coverageType: 'personal-accident-provisional-cover',
                currency: $recognition->currency,
                effectiveAt: $recognition->settled_at,
                expiresAt: $recognition->settled_at->addHours($coverageDurationHours),
                coverageAmountMinor: $insuredAmountMinor,
                terms: [
                    'plan' => $productName,
                    'premium_minor' => $premiumMinor,
                    'coverage_duration_hours' => $coverageDurationHours,
                    'contract_authority' => 'demonstration_only',
                ],
                authorization: [
                    'authority' => 'x-change-browser-scenario-driver',
                    'authority_reference' => (string) data_get($run, 'reference'),
                ],
            ),
            'recognized_settlement_payment_qualified',
        );
    }

    /** @param array<string, mixed> $publication */
    private function decidePublished(CampaignPaymentRecognition $recognition, array $publication): CampaignCoverageDecisionData
    {
        $plan = $publication['plan'];
        if ($recognition->gross_amount_minor !== $plan['premium_minor']
            || $recognition->currency !== $plan['currency']
            || $recognition->provider_status !== 'settled'
            || ! $recognition->destination_verified || $recognition->settled_at === null) {
            return CampaignCoverageDecisionData::ineligible('campaign_or_payment_not_qualified');
        }

        return CampaignCoverageDecisionData::eligible(new ProvisionalCoverageTermsData(
            driverId: self::DRIVER_ID,
            driverVersion: self::DRIVER_VERSION,
            coverageType: 'personal-accident-provisional-cover',
            currency: $recognition->currency,
            effectiveAt: $recognition->settled_at,
            expiresAt: $recognition->settled_at->addDays($plan['coverage']['duration_days']),
            coverageAmountMinor: $plan['benefit_minor'],
            terms: [
                'plan' => $plan['title'],
                'plan_code' => $plan['code'], 'plan_version' => $plan['version'],
                'premium_minor' => $plan['premium_minor'],
                'coverage_duration_hours' => $plan['coverage']['duration_days'] * 24,
                'contract_authority' => 'demonstration_only',
            ],
            authorization: [
                'authority' => 'x-change-campaign-workflow-publication',
                'authority_reference' => $publication['publication_reference'],
            ],
        ), 'recognized_settlement_payment_qualified');
    }
}
