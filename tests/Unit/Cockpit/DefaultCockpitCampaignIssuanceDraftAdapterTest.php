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
