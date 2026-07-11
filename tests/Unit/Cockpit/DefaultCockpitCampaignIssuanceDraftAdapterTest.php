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
