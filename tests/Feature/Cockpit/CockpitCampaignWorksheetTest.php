<?php

declare(strict_types=1);

use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;

it('shows only the authenticated owner campaign worksheet summaries', function () {
    $owner = actingAsTestUser();
    $repository = app(CampaignWorksheetRepository::class);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.campaigns.index'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Campaigns')
        ->assertJsonPath('props.worksheets', []);

    $this->post(route('x-change.cockpit.campaigns.store'), [
        'name' => 'July Payroll',
        'profile' => 'payroll',
        'fulfillment_mode' => 'pay_code_distribution',
        'delivery_plan' => ['csv'],
    ])->assertRedirect(route('x-change.cockpit.campaigns.index'))
        ->assertSessionHas('campaign_notice', 'July Payroll is ready for beneficiary entries.');

    $worksheets = $repository->summariesForOwner($owner->getMorphClass(), (string) $owner->getKey());

    expect($worksheets)->toHaveCount(1)
        ->and($worksheets[0]->name)->toBe('July Payroll')
        ->and($worksheets[0]->beneficiaryCount)->toBe(0);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.campaigns.index'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Campaigns')
        ->assertJsonPath('props.worksheets.0.name', 'July Payroll')
        ->assertJsonPath('props.worksheets.0.beneficiary_count', 0)
        ->assertJsonMissingPath('props.worksheets.0.beneficiary');
});

it('does not let a worksheet creator supply beneficiary or approval facts', function () {
    actingAsTestUser();

    $this->from(route('x-change.cockpit.campaigns.index'))
        ->post(route('x-change.cockpit.campaigns.store'), [
            'name' => 'Invalid Campaign',
            'profile' => 'payroll',
            'fulfillment_mode' => 'pay_code_distribution',
            'delivery_plan' => ['csv'],
            'beneficiary_count' => 1000,
            'principal_minor' => 50_000_000,
            'status' => 'approved',
        ])->assertRedirect(route('x-change.cockpit.campaigns.index'));

    expect(app(CampaignWorksheetRepository::class)
        ->summariesForOwner(auth()->user()->getMorphClass(), (string) auth()->id()))
        ->toHaveCount(1)
        ->and(app(CampaignWorksheetRepository::class)
            ->summariesForOwner(auth()->user()->getMorphClass(), (string) auth()->id())[0]->beneficiaryCount)
        ->toBe(0);
});
