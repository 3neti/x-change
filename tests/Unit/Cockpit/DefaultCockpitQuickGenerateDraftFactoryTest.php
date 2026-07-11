<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Cockpit\DefaultCockpitQuickGenerateDraftFactory;

it('creates an issuance draft from the existing quick generate form payload', function () {
    $draft = (new DefaultCockpitQuickGenerateDraftFactory)->fromPayload([
        'cash' => [
            'amount' => '25.00',
            'currency' => 'PHP',
            'validation' => [
                'mobile' => '09173011987',
            ],
        ],
        'inputs' => [
            'fields' => ['mobile'],
        ],
        'count' => 2,
        'feedback' => [
            'mobile' => '09173011987',
            'email' => 'beneficiary@example.test',
            'webhook' => 'https://example.test/hook',
        ],
        'rider' => [
            'message' => 'Quick Generate Wave 10A',
            'url' => 'https://example.test/claim',
            'splash' => '<p>Issued</p>',
            'splash_timeout' => 5,
        ],
        'metadata' => [
            'custom' => [
                'cockpit' => [
                    'template_key' => 'money-changer',
                    'source' => 'cockpit.quick-generate',
                ],
            ],
        ],
        '_meta' => [
            'idempotency_key' => 'idem-payload',
            'correlation_id' => 'corr-payload',
        ],
    ], idempotencyKey: 'idem-explicit', correlationId: 'corr-explicit');

    expect($draft->template_key)->toBe('money-changer')
        ->and($draft->amount)->toBe('25.00')
        ->and($draft->currency)->toBe('PHP')
        ->and($draft->count)->toBe(2)
        ->and($draft->recipient_reference)->toBe('09173011987')
        ->and($draft->purpose)->toBe('Quick Generate Wave 10A')
        ->and($draft->idempotency_key)->toBe('idem-explicit')
        ->and($draft->correlation_id)->toBe('corr-explicit')
        ->and($draft->feedback)->toMatchArray([
            'mobile' => '09173011987',
            'email' => 'beneficiary@example.test',
            'webhook' => 'https://example.test/hook',
        ])
        ->and($draft->rider)->toMatchArray([
            'message' => 'Quick Generate Wave 10A',
            'url' => 'https://example.test/claim',
            'splash' => '<p>Issued</p>',
            'splash_timeout' => 5,
        ])
        ->and($draft->validation)->toBe(['mobile' => '09173011987'])
        ->and($draft->input_fields)->toBe(['mobile'])
        ->and(data_get($draft->metadata, 'custom.cockpit.source'))->toBe('cockpit.quick-generate')
        ->and($draft->toArray())->not->toHaveKey('provider_payload')
        ->and($draft->toArray())->not->toHaveKey('wallet')
        ->and($draft->toArray())->not->toHaveKey('raw_payload');
});

it('uses legacy quick generate defaults when optional fields are absent', function () {
    $draft = (new DefaultCockpitQuickGenerateDraftFactory)->fromPayload([
        'cash' => [
            'amount' => 25,
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'mobile' => '',
        ],
        'rider' => [
            'message' => '',
        ],
        'metadata' => [],
    ]);

    expect($draft->template_key)->toBe('money-changer')
        ->and($draft->amount)->toBe(25)
        ->and($draft->currency)->toBe('PHP')
        ->and($draft->count)->toBe(1)
        ->and($draft->recipient_reference)->toBeNull()
        ->and($draft->purpose)->toBeNull()
        ->and($draft->feedback)->toMatchArray([
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ])
        ->and($draft->validation)->toBe([])
        ->and($draft->input_fields)->toBe([])
        ->and(data_get($draft->metadata, 'custom.cockpit.source'))->toBe('cockpit.quick-generate');
});

it('accepts campaign context metadata without mutating campaigns', function () {
    $draft = (new DefaultCockpitQuickGenerateDraftFactory)->fromPayload([
        'cash' => [
            'amount' => '250.00',
            'currency' => 'PHP',
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'mobile' => '09173011987',
        ],
        'rider' => [
            'message' => 'Campaign beneficiary payout',
        ],
        'metadata' => [
            'campaign' => [
                'planning_key' => 'plan-wave-10f',
                'execution_id' => 'exec-wave-10f',
                'campaign_id' => 'campaign-wave-10f',
                'audience_id' => 'audience-wave-10f',
                'recipient_id' => 'recipient-wave-10f',
                'source' => 'x-campaign',
            ],
            'custom' => [
                'cockpit' => [
                    'template_key' => 'ofw-remittance',
                ],
            ],
        ],
    ]);

    expect($draft->hasCampaignContext())->toBeTrue()
        ->and($draft->campaign?->planning_key)->toBe('plan-wave-10f')
        ->and($draft->campaign?->execution_id)->toBe('exec-wave-10f')
        ->and($draft->campaign?->campaign_id)->toBe('campaign-wave-10f')
        ->and($draft->campaign?->audience_id)->toBe('audience-wave-10f')
        ->and($draft->campaign?->recipient_id)->toBe('recipient-wave-10f')
        ->and($draft->campaign?->source)->toBe('x-campaign');
});
