<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceCampaignContextData;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitIssuanceDraftAuditMetadataBuilder;

it('builds safe audit metadata for cockpit issuance drafts', function () {
    $metadata = (new DefaultCockpitIssuanceDraftAuditMetadataBuilder)->build(new CockpitIssuanceDraftData(
        template_key: 'ofw-remittance',
        amount: 1500,
        recipient_reference: '09173011987',
        correlation_id: 'corr-audit',
        campaign: new CockpitIssuanceCampaignContextData(
            planning_key: 'plan-001',
            execution_id: 'exec-001',
            campaign_id: 'campaign-001',
            source: 'x-campaign',
        ),
        feedback: ['mobile' => '09173011987'],
        validation: ['secret' => '123456'],
    ));

    expect($metadata->schema)->toBe('x-change.cockpit.issuance-draft-audit.v1')
        ->and($metadata->safe)->toMatchArray([
            'template_key' => 'ofw-remittance',
            'amount' => 1500,
            'currency' => 'PHP',
            'campaign' => [
                'planning_key' => 'plan-001',
                'execution_id' => 'exec-001',
                'campaign_id' => 'campaign-001',
                'source' => 'x-campaign',
            ],
            'correlation_id' => 'corr-audit',
        ])
        ->and($metadata->redactions['recipient_reference'])->toBe('redacted')
        ->and($metadata->toArray())->not->toContain('09173011987')
        ->and($metadata->toArray())->not->toContain('123456');
});
