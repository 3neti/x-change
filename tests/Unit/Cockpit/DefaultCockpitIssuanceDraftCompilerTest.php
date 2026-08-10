<?php

declare(strict_types=1);

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceCampaignContextData;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitCampaignIssuanceDraftAdapter;
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
            'mobile' => null,
            'webhook' => null,
        ],
        'rider' => [
            'message' => 'Counter cash-out',
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

it('preserves only explicitly selected feedback destinations', function (): void {
    $payload = (new DefaultCockpitIssuanceDraftCompiler)->compile(new CockpitIssuanceDraftData(
        template_key: 'money-changer',
        amount: '25.00',
        recipient_reference: '09173011987',
        feedback: [
            'email' => 'issuer@example.com',
            'mobile' => '+639285243656',
            'webhook' => 'https://example.test/redemption-feedback',
        ],
    ));

    expect(data_get($payload, 'feedback'))->toBe([
        'email' => 'issuer@example.com',
        'mobile' => '+639285243656',
        'webhook' => 'https://example.test/redemption-feedback',
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
        ->and(data_get($payload, 'feedback.mobile'))->toBeNull()
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

it('compiles a single campaign recipient draft into a GeneratePayCodeRequest compatible payload', function (): void {
    $draft = (new DefaultCockpitCampaignIssuanceDraftAdapter)->fromCampaignContext([
        'planning_key' => 'plan-wave-51',
        'execution_id' => 'exec-wave-51',
        'campaign_id' => 'campaign-wave-51',
        'audience_id' => 'audience-wave-51',
        'recipient_id' => 'recipient-wave-51',
        'source' => 'x-campaign',
        'template_intent' => 'campaign-payout',
        'recipient' => [
            'reference' => 'BEN-WAVE-51',
            'mobile_number' => '09173011987',
            'email_address' => 'beneficiary51@example.test',
        ],
        'allocation' => [
            'amount' => '500.00',
            'currency' => 'PHP',
            'purpose' => 'Wave 51 campaign payout',
        ],
    ]);

    $payload = (new DefaultCockpitIssuanceDraftCompiler(new DefaultCockpitIssuanceTemplateRegistry))->compile($draft);
    $validator = (new Factory(new Translator(new ArrayLoader, 'en')))
        ->make($payload, (new GeneratePayCodeRequest)->rules());

    expect($validator->fails())->toBeFalse()
        ->and(data_get($payload, 'cash.amount'))->toBe('500.00')
        ->and(data_get($payload, 'cash.currency'))->toBe('PHP')
        ->and(data_get($payload, 'cash.validation.mobile'))->toBe('09173011987')
        ->and(data_get($payload, 'inputs.fields'))->toBe(['mobile'])
        ->and(data_get($payload, 'feedback.mobile'))->toBe('09173011987')
        ->and(data_get($payload, 'feedback.email'))->toBe('beneficiary51@example.test')
        ->and(data_get($payload, 'rider.message'))->toBe('Wave 51 campaign payout')
        ->and(data_get($payload, 'metadata.custom.cockpit.template_key'))->toBe('ofw-remittance')
        ->and(data_get($payload, 'metadata.custom.cockpit.source'))->toBe('cockpit.issuance-draft')
        ->and(data_get($payload, 'metadata.campaign.planning_key'))->toBe('plan-wave-51')
        ->and(data_get($payload, 'metadata.campaign.execution_id'))->toBe('exec-wave-51')
        ->and(data_get($payload, 'metadata.campaign.campaign_id'))->toBe('campaign-wave-51')
        ->and(data_get($payload, 'metadata.campaign.audience_id'))->toBe('audience-wave-51')
        ->and(data_get($payload, 'metadata.campaign.recipient_id'))->toBe('recipient-wave-51')
        ->and(data_get($payload, 'metadata.campaign.source'))->toBe('x-campaign')
        ->and($payload)->not->toHaveKey('provider_payload')
        ->and($payload)->not->toHaveKey('wallet')
        ->and($payload)->not->toHaveKey('raw_payload')
        ->and($payload)->not->toHaveKey('campaign_mutation');
});
