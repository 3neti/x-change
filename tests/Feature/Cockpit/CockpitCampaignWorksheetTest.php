<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;
use LBHurtado\XCampaign\Data\CampaignWorksheetData;
use LBHurtado\XCampaign\Data\CampaignWorksheetRowData;
use LBHurtado\XChange\Actions\Campaigns\IssueCampaignWorksheetApprovalPayCode;
use LBHurtado\XChange\Services\Campaigns\CampaignWorksheetAuthorizationExecutionService;

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

it('lets only the worksheet owner add a draft beneficiary with a mobile or bank destination', function () {
    $owner = actingAsTestUser();

    $this->post(route('x-change.cockpit.campaigns.store'), [
        'name' => 'July Assistance',
        'profile' => 'assistance',
        'fulfillment_mode' => 'pay_code_distribution',
        'delivery_plan' => ['csv'],
    ]);

    $worksheet = app(CampaignWorksheetRepository::class)
        ->summariesForOwner($owner->getMorphClass(), (string) $owner->getKey())[0];

    $this->post(route('x-change.cockpit.campaigns.rows.store', $worksheet->reference), [
        'amount_minor' => 12_500,
        'name' => 'Maria Santos',
        'mobile' => '09173011987',
        'delivery_preference' => 'csv',
    ])->assertRedirect(route('x-change.cockpit.campaigns.show', $worksheet->reference))
        ->assertSessionHas('campaign_notice', 'Beneficiary added to the draft worksheet.');

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.campaigns.show', $worksheet->reference))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/CampaignWorksheet')
        ->assertJsonPath('props.worksheet.rows.0.beneficiary.mobile', '09173011987')
        ->assertJsonPath('props.worksheet.rows.0.amount_minor', 12_500);

    actingAsTestUser();

    $this->get(route('x-change.cockpit.campaigns.show', $worksheet->reference))
        ->assertNotFound();
});

it('stages a campaign import for review and applies only a fully valid staged import once', function () {
    $owner = actingAsTestUser();
    app(CampaignWorksheetRepository::class)->put(new CampaignWorksheetData(
        reference: 'campaign-import-01', ownerType: $owner->getMorphClass(), ownerId: (string) $owner->getKey(), profile: 'payroll', name: 'Import Payroll',
    ));

    $this->post(route('x-change.cockpit.campaigns.imports.store', 'campaign-import-01'), [
        'file' => UploadedFile::fake()->createWithContent('beneficiaries.csv', "name,mobile,amount\nMaria,09173011987,1250.00\n"),
    ])->assertRedirect(route('x-change.cockpit.campaigns.show', 'campaign-import-01'));

    $response = $this->withHeader('X-Inertia', 'true')->get(route('x-change.cockpit.campaigns.show', 'campaign-import-01'));
    $response->assertOk()
        ->assertJsonPath('props.imports.0.status', 'staged')
        ->assertJsonPath('props.imports.0.valid_count', 1)
        ->assertJsonPath('props.imports.0.mapping.amount', 'amount');

    $reference = $response->json('props.imports.0.reference');
    $this->post(route('x-change.cockpit.campaigns.imports.apply', ['worksheet' => 'campaign-import-01', 'import' => $reference]))
        ->assertRedirect(route('x-change.cockpit.campaigns.show', 'campaign-import-01'));

    expect(app(CampaignWorksheetRepository::class)->findForOwner('campaign-import-01', $owner->getMorphClass(), (string) $owner->getKey())?->rows)
        ->toHaveCount(1);

    $this->post(route('x-change.cockpit.campaigns.imports.apply', ['worksheet' => 'campaign-import-01', 'import' => $reference]))
        ->assertSessionHas('campaign_notice', 'Campaign worksheet import has already been applied or is unavailable.');
});

it('stages import errors without adding any beneficiary', function () {
    $owner = actingAsTestUser();
    app(CampaignWorksheetRepository::class)->put(new CampaignWorksheetData(
        reference: 'campaign-import-errors', ownerType: $owner->getMorphClass(), ownerId: (string) $owner->getKey(), profile: 'assistance', name: 'Import Errors',
    ));

    $this->post(route('x-change.cockpit.campaigns.imports.store', 'campaign-import-errors'), [
        'file' => UploadedFile::fake()->createWithContent('beneficiaries.csv', "name,amount\nMaria,1250.00\n"),
    ])->assertRedirect();

    $this->withHeader('X-Inertia', 'true')->get(route('x-change.cockpit.campaigns.show', 'campaign-import-errors'))
        ->assertJsonPath('props.imports.0.valid_count', 0)
        ->assertJsonPath('props.imports.0.validation_errors.0.row', 2)
        ->assertJsonPath('props.worksheet.rows', []);
});

it('issues one zero-value settlement approval Pay Code for a frozen worksheet', function () {
    $owner = actingAsTestUser();
    $repository = app(CampaignWorksheetRepository::class);
    $worksheet = $repository->put(new CampaignWorksheetData(
        reference: 'campaign-approval-01', ownerType: $owner->getMorphClass(), ownerId: (string) $owner->getKey(), profile: 'payroll', name: 'Approval Payroll',
        rows: [new CampaignWorksheetRowData(null, 1, ['mobile' => '09173011987'], 12_500)],
    ));
    $repository->freeze((string) $worksheet->reference, $owner->getMorphClass(), (string) $owner->getKey());

    $first = app(IssueCampaignWorksheetApprovalPayCode::class)->handle((string) $worksheet->reference, $owner);
    $second = app(IssueCampaignWorksheetApprovalPayCode::class)->handle((string) $worksheet->reference, $owner);

    expect($first->approval_pay_code)->not->toBeNull()
        ->and($second->getKey())->toBe($first->getKey())
        ->and($first->beneficiary_count)->toBe(1)
        ->and($first->principal_minor)->toBe(12_500);
});

it('issues a planned campaign batch once through the owner Cockpit control', function () {
    $owner = actingAsTestUser();
    $officer = actingAsTestUser();
    $officer->forceFill(['mobile' => '09173011987'])->save();
    $repository = app(CampaignWorksheetRepository::class);

    $worksheet = $repository->put(new CampaignWorksheetData(
        reference: 'campaign-cockpit-issuance-01',
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'payroll',
        name: 'Cockpit Issuance Payroll',
        fulfillmentMode: 'pay_code_distribution',
        rows: [
            new CampaignWorksheetRowData(null, 1, ['mobile' => '09178889999'], 12_500),
            new CampaignWorksheetRowData(null, 2, ['mobile' => '09179998888'], 7_500),
        ],
    ));
    $repository->freeze((string) $worksheet->reference, $owner->getMorphClass(), (string) $owner->getKey());

    $this->actingAs($owner);
    $authorization = app(IssueCampaignWorksheetApprovalPayCode::class)->handle((string) $worksheet->reference, $owner);

    $this->actingAs($officer);
    app(CampaignWorksheetAuthorizationExecutionService::class)->execute(
        Voucher::query()->where('code', $authorization->approval_pay_code)->sole(),
        ['mobile' => '09173011987'],
    );

    $this->actingAs($owner)
        ->post(route('x-change.cockpit.campaigns.fulfillments.pay-codes.store', $worksheet->reference))
        ->assertRedirect(route('x-change.cockpit.campaigns.show', $worksheet->reference))
        ->assertSessionHas('campaign_notice', '2 beneficiary Pay Codes issued. No messages or transfers were sent.');

    $authorization->refresh();

    expect($authorization->fulfillments()->where('status', 'issued')->count())->toBe(2)
        ->and($authorization->fulfillments()->whereNotNull('pay_code')->count())->toBe(2);

    $this->post(route('x-change.cockpit.campaigns.fulfillments.pay-codes.store', $worksheet->reference))
        ->assertRedirect(route('x-change.cockpit.campaigns.show', $worksheet->reference))
        ->assertSessionHas('campaign_notice', '0 beneficiary Pay Codes issued. No messages or transfers were sent.');

    expect($authorization->fulfillments()->where('status', 'issued')->count())->toBe(2)
        ->and($authorization->fulfillments()->whereNotNull('pay_code')->count())->toBe(2);
});
