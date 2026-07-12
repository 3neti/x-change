<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Cockpit\DefaultCockpitCampaignIssuanceDraftAdapter;

it('adapts campaign planning context into a cockpit issuance draft', function () {
    $draft = (new DefaultCockpitCampaignIssuanceDraftAdapter)->fromCampaignContext([
        'planning_key' => 'plan-001',
        'execution_id' => 'exec-001',
        'campaign_id' => 'campaign-001',
        'audience_id' => 'audience-001',
        'recipient_id' => 'recipient-001',
        'template_key' => 'ofw-remittance',
        'amount' => '1500.00',
        'currency' => 'PHP',
        'recipient' => [
            'mobile' => '09173011987',
            'email' => 'recipient@example.test',
        ],
        'purpose' => 'Campaign payout',
        'source' => 'x-campaign',
    ]);

    expect($draft->hasCampaignContext())->toBeTrue()
        ->and($draft->template_key)->toBe('ofw-remittance')
        ->and($draft->amount)->toBe('1500.00')
        ->and($draft->campaign?->planning_key)->toBe('plan-001')
        ->and($draft->feedback)->toMatchArray([
            'mobile' => '09173011987',
            'email' => 'recipient@example.test',
        ]);
});

it('defaults campaign drafts to a safe campaign source without mutating campaign state', function () {
    $draft = (new DefaultCockpitCampaignIssuanceDraftAdapter)->fromCampaignContext([
        'amount' => 25,
    ]);

    expect($draft->campaign?->source)->toBe('x-campaign')
        ->and($draft->template_key)->toBe('ofw-remittance')
        ->and($draft->count)->toBe(1);
});

it('normalizes campaign template intent aliases into quick generate template keys', function (array $campaignContext, string $expectedTemplate): void {
    $draft = (new DefaultCockpitCampaignIssuanceDraftAdapter)->fromCampaignContext($campaignContext + [
        'planning_key' => 'plan-39b',
        'execution_id' => 'exec-39b',
        'amount' => '500.00',
    ]);

    expect($draft->template_key)->toBe($expectedTemplate)
        ->and($draft->metadata['campaign']['template_intent'])->toBe($campaignContext)
        ->and($draft->metadata['campaign']['template_key'])->toBe($expectedTemplate)
        ->and($draft->metadata['campaign']['template_mapping_source'])->toBe('campaign-template-intent');
})->with([
    'money changer product' => [['template_intent' => 'money_changer'], 'money-changer'],
    'cash branch slug' => [['product_key' => 'branch-cash-out'], 'money-changer'],
    'ofw remittance product' => [['product' => ['key' => 'ofw_remittance']], 'ofw-remittance'],
    'remittance template profile' => [['template' => ['profile' => 'remittance']], 'ofw-remittance'],
    'settlement envelope product' => [['template_intent' => 'settlement-envelope'], 'settlement-envelope'],
]);

it('preserves explicit cockpit template keys ahead of campaign template intent aliases', function (): void {
    $draft = (new DefaultCockpitCampaignIssuanceDraftAdapter)->fromCampaignContext([
        'template_key' => 'ofw-remittance',
        'template_intent' => 'money_changer',
        'amount' => '500.00',
    ]);

    expect($draft->template_key)->toBe('ofw-remittance')
        ->and($draft->metadata['campaign']['template_mapping_source'])->toBe('explicit-template-key');
});

it('normalizes campaign recipient and payout aliases into issuance draft fields', function (): void {
    $draft = (new DefaultCockpitCampaignIssuanceDraftAdapter)->fromCampaignContext([
        'planning_key' => 'plan-40b',
        'execution_id' => 'exec-40b',
        'recipient' => [
            'reference' => 'BEN-0001',
            'mobile_number' => '091700000040',
            'email_address' => 'beneficiary40@example.test',
            'name' => 'Beneficiary Forty',
        ],
        'payout' => [
            'amount' => '875.50',
            'currency' => 'PHP',
            'purpose' => 'Recipient payout',
            'message' => 'Your campaign Pay Code is ready.',
        ],
    ]);

    expect($draft->amount)->toBe('875.50')
        ->and($draft->currency)->toBe('PHP')
        ->and($draft->recipient_reference)->toBe('BEN-0001')
        ->and($draft->purpose)->toBe('Recipient payout')
        ->and($draft->feedback)->toMatchArray([
            'mobile' => '091700000040',
            'email' => 'beneficiary40@example.test',
        ])
        ->and($draft->rider['message'])->toBe('Your campaign Pay Code is ready.')
        ->and($draft->metadata['campaign']['recipient_mapping_source'])->toBe('campaign-recipient-context')
        ->and($draft->metadata['campaign']['recipient_context'])->toMatchArray([
            'recipient_reference' => 'BEN-0001',
            'mobile' => '091700000040',
            'email' => 'beneficiary40@example.test',
        ]);
});

it('preserves explicit recipient draft fields ahead of campaign recipient aliases', function (): void {
    $draft = (new DefaultCockpitCampaignIssuanceDraftAdapter)->fromCampaignContext([
        'amount' => '100.00',
        'recipient_reference' => 'EXPLICIT-REF',
        'purpose' => 'Explicit purpose',
        'feedback' => [
            'mobile' => '09999999999',
            'email' => 'explicit@example.test',
        ],
        'rider' => [
            'message' => 'Explicit message',
        ],
        'recipient' => [
            'reference' => 'RECIPIENT-REF',
            'mobile_number' => '091700000040',
            'email_address' => 'beneficiary40@example.test',
        ],
        'payout' => [
            'amount' => '875.50',
            'purpose' => 'Recipient payout',
            'message' => 'Recipient message',
        ],
    ]);

    expect($draft->amount)->toBe('100.00')
        ->and($draft->recipient_reference)->toBe('EXPLICIT-REF')
        ->and($draft->purpose)->toBe('Explicit purpose')
        ->and($draft->feedback)->toMatchArray([
            'mobile' => '09999999999',
            'email' => 'explicit@example.test',
        ])
        ->and($draft->rider['message'])->toBe('Explicit message')
        ->and($draft->metadata['campaign']['recipient_mapping_source'])->toBe('explicit-draft-fields');
});
