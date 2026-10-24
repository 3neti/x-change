<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use InvalidArgumentException;
use LBHurtado\XChange\Data\Configuration\ApplicationJourneyData;
use LBHurtado\XChange\Enums\ApplicationJourneyKind;

final class ApplicationJourneyRouteRegistry
{
    public function routeName(ApplicationJourneyData $journey): string
    {
        $expectedKind = match ($journey->key) {
            'overview', 'campaign_activity', 'lead_activity', 'enrollment_activity',
            'tap_activity', 'sale_activity', 'pay_code_activity', 'local_remittance_activity',
            'offshore_remittance_activity',
            'disbursement_activity', 'exchange_activity' => ApplicationJourneyKind::Inspect,
            'issue_pay_code', 'loan_disbursement', 'exchange_payout' => ApplicationJourneyKind::IssueSingle,
            'send_local_remittance', 'send_offshore_remittance' => ApplicationJourneyKind::FundAndIssue,
            'claim_pay_code', 'claim_disbursement', 'claim_exchange', 'receive_local_remittance',
            'receive_offshore_remittance' => ApplicationJourneyKind::Claim,
            'salary_payout', 'gaming_payout', 'ayuda_payout', 'campaign_distribution' => ApplicationJourneyKind::IssueBatch,
            'lead_capture' => ApplicationJourneyKind::CaptureAndQualify,
            'product_enrollment' => ApplicationJourneyKind::Enroll,
            'fare_collection', 'sale_collection' => ApplicationJourneyKind::Collect,
            'collection_exception' => ApplicationJourneyKind::ResolveException,
            default => throw new InvalidArgumentException("Journey [{$journey->key}] has no runtime route adapter."),
        };
        if ($journey->kind !== $expectedKind) {
            throw new InvalidArgumentException("Journey [{$journey->key}] has an incompatible runtime adapter.");
        }

        return match ($journey->key) {
            'overview' => 'x-change.cockpit.dashboard',
            'issue_pay_code', 'loan_disbursement', 'exchange_payout', 'send_local_remittance',
            'send_offshore_remittance' => 'x-change.cockpit.quick-generate',
            'claim_pay_code', 'claim_disbursement', 'claim_exchange', 'receive_local_remittance',
            'receive_offshore_remittance' => 'x-change.claim.start',
            'salary_payout', 'gaming_payout', 'ayuda_payout', 'campaign_distribution',
            'lead_capture', 'product_enrollment', 'campaign_activity', 'lead_activity',
            'enrollment_activity' => 'x-change.cockpit.campaigns.index',
            'fare_collection', 'sale_collection', 'tap_activity', 'sale_activity',
            'collection_exception' => 'x-change.cockpit.stored-value-collections.index',
            'pay_code_activity', 'local_remittance_activity', 'offshore_remittance_activity', 'disbursement_activity',
            'exchange_activity' => 'x-change.cockpit.pay-codes.index',
            default => throw new InvalidArgumentException("Journey [{$journey->key}] has no runtime route adapter."),
        };
    }
}
