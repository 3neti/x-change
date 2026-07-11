<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitIssuanceDraftAuditMetadataBuilderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftAuditMetadataData;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;

class DefaultCockpitIssuanceDraftAuditMetadataBuilder implements CockpitIssuanceDraftAuditMetadataBuilderContract
{
    public function build(CockpitIssuanceDraftData $draft): CockpitIssuanceDraftAuditMetadataData
    {
        return new CockpitIssuanceDraftAuditMetadataData(
            safe: [
                'template_key' => $draft->template_key,
                'amount' => $draft->amount,
                'currency' => $draft->currency,
                'count' => $draft->count,
                'campaign' => [
                    'planning_key' => $draft->campaign?->planning_key,
                    'execution_id' => $draft->campaign?->execution_id,
                    'campaign_id' => $draft->campaign?->campaign_id,
                    'source' => $draft->campaign?->source,
                ],
                'correlation_id' => $draft->correlation_id,
            ],
            redactions: [
                'recipient_reference' => filled($draft->recipient_reference) ? 'redacted' : 'not-provided',
                'feedback' => 'redacted',
                'validation' => 'redacted',
                'rider' => 'redacted',
                'metadata' => 'safe-summary-only',
                'excludes' => [
                    'provider_payload',
                    'raw_payload',
                    'wallet',
                    'secret',
                    'otp',
                    'token',
                    'account_number',
                ],
            ],
        );
    }
}
