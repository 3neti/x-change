<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceCampaignContextData;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitIssuanceDraftCompiler;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitIssuanceTemplateRegistry;

it('compiles a cockpit issuance draft into a GeneratePayCodeRequest compatible payload', function () {
    $payload = (new DefaultCockpitIssuanceDraftCompiler)->compile(new CockpitIssuanceDraftData(
        template_key: 'money-changer',
        amount: '25.00',
        currency: 'PHP',
        count: 1,
        recipient_reference: '09173011987',
        purpose: 'Counter cash-out',
        idempotency_key: 'idem-compiler',
        correlation_id: 'corr-compiler',
        validation: ['mobile' => '09173011987'],
        input_fields: ['mobile'],
    ));

    expect($payload)->toMatchArray([
        'cash' => [
            'amount' => '25.00',
            'currency' => 'PHP',
            'validation' => ['mobile' => '09173011987'],
        ],
        'inputs' => [
            'fields' => ['mobile'],
        ],
        'count' => 1,
        'feedback' => [
            'email' => null,
            'mobile' => '09173011987',
            'webhook' => null,
        ],
        'rider' => [
            'message' => 'Counter cash-out',
            'url' => null,
            'splash' => null,
            'splash_timeout' => null,
        ],
        '_meta' => [
            'idempotency_key' => 'idem-compiler',
            'correlation_id' => 'corr-compiler',
            'source' => 'cockpit.issuance-draft',
        ],
    ])
        ->and(data_get($payload, 'metadata.custom.cockpit.template_key'))->toBe('money-changer')
        ->and($payload)->not->toHaveKey('provider_payload')
        ->and($payload)->not->toHaveKey('wallet')
        ->and($payload)->not->toHaveKey('raw_payload');
});

it('preserves campaign context as metadata without mutating campaigns', function () {
    $payload = (new DefaultCockpitIssuanceDraftCompiler)->compile(new CockpitIssuanceDraftData(
        template_key: 'ofw-remittance',
        amount: 500,
        campaign: new CockpitIssuanceCampaignContextData(
            planning_key: 'plan-001',
            execution_id: 'exec-001',
            campaign_id: 'campaign-001',
            source: 'x-campaign',
        ),
    ));

    expect(data_get($payload, 'metadata.campaign'))->toMatchArray([
        'planning_key' => 'plan-001',
        'execution_id' => 'exec-001',
        'campaign_id' => 'campaign-001',
        'source' => 'x-campaign',
    ]);
});

it('applies template profile defaults while preserving explicit draft values', function () {
    $payload = (new DefaultCockpitIssuanceDraftCompiler(new DefaultCockpitIssuanceTemplateRegistry))->compile(new CockpitIssuanceDraftData(
        template_key: 'ofw-remittance',
        amount: '500.00',
        currency: 'PHP',
        count: 1,
        recipient_reference: '09173011987',
        rider: [
            'message' => 'Explicit remittance message',
        ],
        validation: [
            'mobile' => '09173011987',
        ],
    ));

    expect(data_get($payload, 'inputs.fields'))->toBe(['mobile'])
        ->and(data_get($payload, 'cash.validation.mobile'))->toBe('09173011987')
        ->and(data_get($payload, 'feedback.mobile'))->toBe('09173011987')
        ->and(data_get($payload, 'rider.message'))->toBe('Explicit remittance message')
        ->and(data_get($payload, 'metadata.template.purpose'))->toBe('remittance');
});

it('uses template rider defaults when the draft has no message', function () {
    $payload = (new DefaultCockpitIssuanceDraftCompiler(new DefaultCockpitIssuanceTemplateRegistry))->compile(new CockpitIssuanceDraftData(
        template_key: 'money-changer',
        amount: '25.00',
        currency: 'PHP',
    ));

    expect(data_get($payload, 'rider.message'))->toBe('Your Pay Code is ready.')
        ->and(data_get($payload, 'metadata.template.purpose'))->toBe('branch-counter-cash-out');
});
