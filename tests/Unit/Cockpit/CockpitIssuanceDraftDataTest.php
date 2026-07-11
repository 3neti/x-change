<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceCampaignContextData;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;

it('represents a cockpit template issuance draft without executing issuance', function () {
    $draft = new CockpitIssuanceDraftData(
        template_key: 'money-changer',
        amount: '25.00',
        currency: 'PHP',
        count: 1,
        recipient_reference: '09173011987',
        purpose: 'Counter cash-out',
        idempotency_key: 'draft-idempotency-key',
        correlation_id: 'corr-draft',
        feedback: ['mobile' => '09173011987'],
        rider: ['message' => 'Claim your Pay Code.'],
        validation: ['mobile' => '09173011987'],
        input_fields: ['mobile'],
        metadata: ['source' => 'cockpit.quick-generate'],
    );

    expect($draft->schema)->toBe('x-change.cockpit.issuance-draft.v1')
        ->and($draft->status)->toBe('draft')
        ->and($draft->template_key)->toBe('money-changer')
        ->and($draft->amount)->toBe('25.00')
        ->and($draft->hasCampaignContext())->toBeFalse()
        ->and($draft->toArray())->not->toHaveKey('provider_payload')
        ->and($draft->toArray())->not->toHaveKey('wallet')
        ->and($draft->toArray())->not->toHaveKey('raw_payload');
});

it('represents a future campaign-backed issuance draft context', function () {
    $draft = new CockpitIssuanceDraftData(
        template_key: 'ofw-remittance',
        amount: 1000,
        campaign: new CockpitIssuanceCampaignContextData(
            planning_key: 'campaign-plan-001',
            execution_id: 'exec-001',
            campaign_id: 'campaign-001',
            audience_id: 'audience-001',
            recipient_id: 'recipient-001',
            source: 'x-campaign',
        ),
    );

    expect($draft->hasCampaignContext())->toBeTrue()
        ->and($draft->campaign?->source)->toBe('x-campaign')
        ->and($draft->toArray()['campaign'])->toMatchArray([
            'planning_key' => 'campaign-plan-001',
            'execution_id' => 'exec-001',
            'campaign_id' => 'campaign-001',
        ]);
});
