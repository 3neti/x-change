<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceCampaignContextData;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateCampaignContextData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateReadModelData;

it('defines a read only campaign context contract for quick generate', function () {
    $context = new CockpitQuickGenerateCampaignContextData(
        status: 'available',
        authorized: true,
        planning_key: 'plan-35b',
        execution_id: 'exec-35b',
        campaign_id: 'campaign-35b',
        audience_id: 'audience-35b',
        recipient_id: 'recipient-35b',
        source: 'x-campaign',
        draft: new CockpitIssuanceDraftData(
            template_key: 'ofw-remittance',
            amount: '500.00',
            currency: 'PHP',
            recipient_reference: '09173011987',
            purpose: 'Campaign payout',
            campaign: new CockpitIssuanceCampaignContextData(
                planning_key: 'plan-35b',
                execution_id: 'exec-35b',
                campaign_id: 'campaign-35b',
                audience_id: 'audience-35b',
                recipient_id: 'recipient-35b',
                source: 'x-campaign',
            ),
        ),
        redactions: [
            'payloads' => 'campaign-context-prefill-only',
            'excluded' => ['raw_payload', 'provider_payload', 'wallet', 'campaign_payload'],
        ],
    );

    $payload = $context->toArray();

    expect($payload)->toMatchArray([
        'schema' => 'x-change.cockpit.quick-generate-campaign-context.v1',
        'status' => 'available',
        'authorized' => true,
        'read_only' => true,
        'mutates_campaign' => false,
        'planning_key' => 'plan-35b',
        'execution_id' => 'exec-35b',
        'campaign_id' => 'campaign-35b',
        'audience_id' => 'audience-35b',
        'recipient_id' => 'recipient-35b',
        'source' => 'x-campaign',
        'redactions' => [
            'payloads' => 'campaign-context-prefill-only',
            'excluded' => ['raw_payload', 'provider_payload', 'wallet', 'campaign_payload'],
        ],
    ])
        ->and(data_get($payload, 'draft.schema'))->toBe('x-change.cockpit.issuance-draft.v1')
        ->and(data_get($payload, 'draft.template_key'))->toBe('ofw-remittance')
        ->and(data_get($payload, 'draft.amount'))->toBe('500.00')
        ->and(data_get($payload, 'draft.currency'))->toBe('PHP')
        ->and(data_get($payload, 'draft.recipient_reference'))->toBe('09173011987')
        ->and(data_get($payload, 'draft.purpose'))->toBe('Campaign payout')
        ->and($payload)->not->toHaveKeys([
            'campaign_payload',
            'provider_payload',
            'wallet',
            'raw_payload',
        ]);
});

it('attaches campaign context to quick generate read models safely by default', function () {
    $readModel = new CockpitQuickGenerateReadModelData(status: 'available', authorized: true);

    expect($readModel->toArray())->toMatchArray([
        'campaign_context' => [
            'schema' => 'x-change.cockpit.quick-generate-campaign-context.v1',
            'status' => 'not_wired',
            'authorized' => false,
            'read_only' => true,
            'mutates_campaign' => false,
            'planning_key' => null,
            'execution_id' => null,
            'campaign_id' => null,
            'audience_id' => null,
            'recipient_id' => null,
            'source' => null,
            'draft' => null,
            'redactions' => ['payloads' => 'not-loaded'],
        ],
    ]);
});
