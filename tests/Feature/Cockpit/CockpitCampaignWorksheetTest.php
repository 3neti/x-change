<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetImportRepository;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetIntakeRepository;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;
use LBHurtado\XCampaign\Data\CampaignWorksheetData;
use LBHurtado\XCampaign\Data\CampaignWorksheetRowData;
use LBHurtado\XChange\Actions\Campaigns\ConvergeCampaignFeedbackDelivery;
use LBHurtado\XChange\Actions\Campaigns\DispatchCampaignFeedback;
use LBHurtado\XChange\Actions\Campaigns\IssueCampaignWorksheetApprovalPayCode;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Jobs\Campaigns\ConvergeCampaignFeedbackDeliveryJob;
use LBHurtado\XChange\Jobs\Campaigns\DispatchCampaignFeedbackJob;
use LBHurtado\XChange\Jobs\Feedback\DeliverQueuedFeedbackSmsJob;
use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use LBHurtado\XChange\Services\Campaigns\CampaignWorksheetAuthorizationExecutionService;
use LBHurtado\XFeedback\Contracts\FeedbackChannelRegistryContract;
use LBHurtado\XFeedback\Drivers\SmsFeedbackChannelDriver;
use LBHurtado\XFeedback\Mail\FeedbackEmailMessage;
use LBHurtado\XFeedback\Models\FeedbackDeliveryRecord;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function fundCampaignOwnerClientFunds(
    mixed $owner,
    int $amountMinor,
    string $evidenceReference,
): void {
    enableNetbankTreasuryForTests();
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $owner,
        ['netbank-primary'],
    );
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$owner->wallet->uuid,
        provider: 'netbank',
        amountMinor: $amountMinor,
        currency: 'PHP',
        evidenceReference: $evidenceReference,
    );
}

it('keeps campaign messaging behind the encrypted x-change feedback queue boundary', function () {
    config()->set('x-change.redemption.feedback.queue', 'default');

    $job = new DispatchCampaignFeedbackJob(123, 'recipient@example.test');
    $campaignSources = implode('', [
        file_get_contents(__DIR__.'/../../../src/Actions/Campaigns/DispatchCampaignFeedback.php'),
        file_get_contents(__DIR__.'/../../../src/Actions/Campaigns/DispatchCampaignPayCodeDeliveries.php'),
        file_get_contents(__DIR__.'/../../../src/Actions/Campaigns/SendCampaignApprovalPayCode.php'),
    ]);

    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and($job)->toBeInstanceOf(ShouldBeEncrypted::class)
        ->and($job->queue)->toBe('x-change-feedback')
        ->and($campaignSources)->not->toContain('Mail::')
        ->and($campaignSources)->not->toContain('LBHurtado\\SMS')
        ->and($campaignSources)->not->toContain('FeedbackDeliveryAttemptRuntimeContract');
});

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

it('scrutinizes a worksheet before creating a campaign and converts selected valid rows', function () {
    $owner = actingAsTestUser();

    $this->post(route('x-change.cockpit.campaigns.intakes.store'), [
        'file' => UploadedFile::fake()->createWithContent(
            'july-payroll.csv',
            "name,mobile,amount\nMaria,09173011987,100.00\nMissing,,50.00\n",
        ),
    ])->assertRedirect(route('x-change.cockpit.campaigns.index'))
        ->assertSessionHas('campaign_notice');

    expect(app(CampaignWorksheetRepository::class)
        ->summariesForOwner($owner->getMorphClass(), (string) $owner->getKey()))
        ->toBe([]);

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.campaigns.index'))
        ->assertOk()
        ->assertJsonPath('props.active_intake.suggestion.profile', 'payroll')
        ->assertJsonPath('props.active_intake.suggestion.fulfillment_mode', 'pay_code_distribution')
        ->assertJsonPath('props.active_intake.valid_count', 1)
        ->assertJsonPath('props.active_intake.invalid_count', 1)
        ->assertJsonPath('props.active_intake.valid_principal_minor', 10_000);

    $intake = $response->json('props.active_intake.reference');
    $this->post(route('x-change.cockpit.campaigns.intakes.convert', $intake), [
        'name' => 'July Payroll',
        'profile' => 'payroll',
        'fulfillment_mode' => 'pay_code_distribution',
        'included_source_rows' => [2],
        'exclude_invalid_rows' => true,
    ])->assertRedirect();

    $worksheet = app(CampaignWorksheetRepository::class)
        ->summariesForOwner($owner->getMorphClass(), (string) $owner->getKey())[0];

    expect($worksheet->name)->toBe('July Payroll')
        ->and($worksheet->beneficiaryCount)->toBe(1)
        ->and($worksheet->principalMinor)->toBe(10_000);
});

it('suggests direct bank transfer when bank destination columns are present', function () {
    actingAsTestUser();

    $this->post(route('x-change.cockpit.campaigns.intakes.store'), [
        'file' => UploadedFile::fake()->createWithContent(
            'assistance-bank-list.csv',
            "name,bank,account number,amount\nMaria,GCash,09173011987,500.00\n",
        ),
    ])->assertRedirect();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.campaigns.index'))
        ->assertOk()
        ->assertJsonPath('props.active_intake.suggestion.profile', 'assistance')
        ->assertJsonPath('props.active_intake.suggestion.fulfillment_mode', 'direct_bank_transfer')
        ->assertJsonPath('props.active_intake.valid_count', 1);
});

it('does not create duplicate campaigns when the same intake conversion is replayed', function () {
    $owner = actingAsTestUser();
    $file = UploadedFile::fake()->createWithContent(
        'beneficiaries.csv',
        "mobile,amount\n09173011987,100.00\n",
    );
    $this->post(route('x-change.cockpit.campaigns.intakes.store'), ['file' => $file]);
    $intake = app(CampaignWorksheetIntakeRepository::class)
        ->activeForOwner($owner->getMorphClass(), (string) $owner->getKey());

    $payload = [
        'name' => 'Replay Safe Campaign',
        'profile' => 'payroll',
        'fulfillment_mode' => 'pay_code_distribution',
        'included_source_rows' => [2],
        'exclude_invalid_rows' => false,
    ];
    $this->post(route('x-change.cockpit.campaigns.intakes.convert', $intake?->reference), $payload)
        ->assertRedirect();
    $this->post(route('x-change.cockpit.campaigns.intakes.convert', $intake?->reference), $payload)
        ->assertNotFound();

    expect(app(CampaignWorksheetRepository::class)
        ->summariesForOwner($owner->getMorphClass(), (string) $owner->getKey()))
        ->toHaveCount(1);
});

it('lets the owner delete a draft campaign and its working data', function () {
    $owner = actingAsTestUser();
    $repository = app(CampaignWorksheetRepository::class);
    $worksheet = $repository->put(new CampaignWorksheetData(
        reference: 'campaign-delete-draft',
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'payroll',
        name: 'Mistaken Payroll',
        rows: [new CampaignWorksheetRowData(null, 1, ['mobile' => '09173011987'], 10_000)],
    ));

    $this->delete(route('x-change.cockpit.campaigns.destroy', $worksheet->reference))
        ->assertRedirect(route('x-change.cockpit.campaigns.index'))
        ->assertSessionHas('campaign_notice', 'Mistaken Payroll was deleted.');

    expect($repository->findForOwner(
        (string) $worksheet->reference,
        $owner->getMorphClass(),
        (string) $owner->getKey(),
    ))->toBeNull();
});

it('hides another owner campaign and protects every non-draft campaign from deletion', function () {
    $owner = actingAsTestUser();
    $repository = app(CampaignWorksheetRepository::class);
    $worksheet = $repository->put(new CampaignWorksheetData(
        reference: 'campaign-delete-protected',
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'payroll',
        name: 'Protected Payroll',
        rows: [new CampaignWorksheetRowData(null, 1, ['mobile' => '09173011987'], 10_000)],
    ));
    $repository->freeze((string) $worksheet->reference, $owner->getMorphClass(), (string) $owner->getKey());

    $this->delete(route('x-change.cockpit.campaigns.destroy', $worksheet->reference))
        ->assertRedirect(route('x-change.cockpit.campaigns.index'))
        ->assertSessionHasErrors(['campaign' => 'Only a draft Campaign may be deleted.']);

    actingAsTestUser();
    $this->delete(route('x-change.cockpit.campaigns.destroy', $worksheet->reference))
        ->assertNotFound();

    expect($repository->findForOwner(
        (string) $worksheet->reference,
        $owner->getMorphClass(),
        (string) $owner->getKey(),
    ))->not->toBeNull();
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
        ->assertJsonPath('props.worksheet.rows.0.amount_minor', 12_500)
        ->assertJsonPath('props.direct_bank_transfer_enabled', false)
        ->assertJsonPath('props.delivery.channels.sms', false)
        ->assertJsonPath('props.delivery.channels.email', false);

    actingAsTestUser();

    $this->get(route('x-change.cockpit.campaigns.show', $worksheet->reference))
        ->assertNotFound();
});

it('accepts beneficiary amounts in major peso units and stores exact minor units', function () {
    $owner = actingAsTestUser();
    app(CampaignWorksheetRepository::class)->put(new CampaignWorksheetData(
        reference: 'campaign-major-amount',
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'payroll',
        name: 'Major Amount Payroll',
    ));

    $this->post(route('x-change.cockpit.campaigns.rows.store', 'campaign-major-amount'), [
        'amount' => '₱1,250.50',
        'name' => 'Maria Santos',
        'mobile' => '09173011987',
        'delivery_preference' => 'sms',
    ])->assertRedirect(route('x-change.cockpit.campaigns.show', 'campaign-major-amount'));

    expect(app(CampaignWorksheetRepository::class)
        ->findForOwner('campaign-major-amount', $owner->getMorphClass(), (string) $owner->getKey())
        ?->rows[0]->amountMinor)->toBe(125_050);
});

it('rejects ambiguous and over-precise beneficiary amounts', function (string $amount) {
    $owner = actingAsTestUser();
    app(CampaignWorksheetRepository::class)->put(new CampaignWorksheetData(
        reference: 'campaign-invalid-major-amount',
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'payroll',
        name: 'Invalid Major Amount Payroll',
    ));

    $this->post(route('x-change.cockpit.campaigns.rows.store', 'campaign-invalid-major-amount'), [
        'amount' => $amount,
        'mobile' => '09173011987',
        'delivery_preference' => 'sms',
    ])->assertSessionHasErrors('amount');
})->with(['1.234', '1,2', '0']);

it('stages a campaign import for review and applies valid rows once', function () {
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
        ->assertJsonPath('props.imports.0.unapplied_valid_count', 1)
        ->assertJsonPath('props.imports.0.valid_principal_minor', 125_000)
        ->assertJsonPath('props.imports.0.mapping.amount', 'amount');

    $reference = $response->json('props.imports.0.reference');
    $this->post(route('x-change.cockpit.campaigns.imports.apply', ['worksheet' => 'campaign-import-01', 'import' => $reference]))
        ->assertRedirect(route('x-change.cockpit.campaigns.show', 'campaign-import-01'));

    expect(app(CampaignWorksheetRepository::class)->findForOwner('campaign-import-01', $owner->getMorphClass(), (string) $owner->getKey())?->rows)
        ->toHaveCount(1);

    $this->post(route('x-change.cockpit.campaigns.imports.apply', ['worksheet' => 'campaign-import-01', 'import' => $reference]))
        ->assertSessionHas('campaign_notice', 'Campaign worksheet import has already been applied or is unavailable.');
});

it('preserves quoted CSV line breaks while staging rows', function () {
    $owner = actingAsTestUser();
    app(CampaignWorksheetRepository::class)->put(new CampaignWorksheetData(
        reference: 'campaign-import-multiline', ownerType: $owner->getMorphClass(), ownerId: (string) $owner->getKey(), profile: 'payroll', name: 'Import Multiline',
    ));

    $this->post(route('x-change.cockpit.campaigns.imports.store', 'campaign-import-multiline'), [
        'file' => UploadedFile::fake()->createWithContent(
            'beneficiaries.csv',
            "mobile,amount,remarks\n09173011987,100.00,\"First line\nSecond line\"\n",
        ),
    ])->assertRedirect();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.campaigns.show', 'campaign-import-multiline'))
        ->assertJsonPath('props.imports.0.unapplied_valid_count', 1)
        ->assertJsonPath('props.imports.0.preview.0.normalized.beneficiary.remarks', "First line\nSecond line");
});

it('requires the current import preview to be resolved before staging another file', function () {
    $owner = actingAsTestUser();
    app(CampaignWorksheetRepository::class)->put(new CampaignWorksheetData(
        reference: 'campaign-import-single-preview', ownerType: $owner->getMorphClass(), ownerId: (string) $owner->getKey(), profile: 'payroll', name: 'Single Preview',
    ));

    $route = route('x-change.cockpit.campaigns.imports.store', 'campaign-import-single-preview');
    $this->post($route, [
        'file' => UploadedFile::fake()->createWithContent('first.csv', "mobile,amount\n09173011987,100.00\n"),
    ])->assertRedirect();

    $this->post($route, [
        'file' => UploadedFile::fake()->createWithContent('second.csv', "mobile,amount\n09170000001,200.00\n"),
    ])->assertSessionHasErrors([
        'file' => 'Add or discard the current preview before uploading another file.',
    ]);

    expect(app(CampaignWorksheetImportRepository::class)
        ->forOwner('campaign-import-single-preview', $owner->getMorphClass(), (string) $owner->getKey()))
        ->toHaveCount(1);
});

it('rejects empty import files and duplicate source mappings', function () {
    $owner = actingAsTestUser();
    app(CampaignWorksheetRepository::class)->put(new CampaignWorksheetData(
        reference: 'campaign-import-guards', ownerType: $owner->getMorphClass(), ownerId: (string) $owner->getKey(), profile: 'payroll', name: 'Import Guards',
    ));

    $this->post(route('x-change.cockpit.campaigns.imports.store', 'campaign-import-guards'), [
        'file' => UploadedFile::fake()->createWithContent('empty.csv', "mobile,amount\n"),
    ])->assertSessionHasErrors(['file' => 'The file contains no beneficiary rows.']);

    $this->post(route('x-change.cockpit.campaigns.imports.store', 'campaign-import-guards'), [
        'file' => UploadedFile::fake()->createWithContent('beneficiaries.csv', "contact,amount\n09173011987,100.00\n"),
    ])->assertRedirect();

    $staged = app(CampaignWorksheetImportRepository::class)
        ->forOwner('campaign-import-guards', $owner->getMorphClass(), (string) $owner->getKey())[0];

    $this->patch(route('x-change.cockpit.campaigns.imports.mapping.update', [
        'worksheet' => 'campaign-import-guards',
        'import' => $staged->reference,
    ]), [
        'mapping' => ['mobile' => 'contact', 'name' => 'contact', 'amount' => 'amount'],
        'default_wallet' => 'GCash',
        'default_delivery_preference' => 'manual',
    ])->assertSessionHasErrors([
        'mapping' => 'Each source column may be mapped to only one beneficiary field.',
    ]);
});

it('stages valid and invalid rows independently and applies only valid rows', function () {
    $owner = actingAsTestUser();
    app(CampaignWorksheetRepository::class)->put(new CampaignWorksheetData(
        reference: 'campaign-import-errors', ownerType: $owner->getMorphClass(), ownerId: (string) $owner->getKey(), profile: 'assistance', name: 'Import Errors',
    ));

    $this->post(route('x-change.cockpit.campaigns.imports.store', 'campaign-import-errors'), [
        'file' => UploadedFile::fake()->createWithContent(
            'beneficiaries.csv',
            "name,mobile,amount\nMaria,09173011987,1250.00\nJose,,250.00\n",
        ),
    ])->assertRedirect();

    $response = $this->withHeader('X-Inertia', 'true')->get(route('x-change.cockpit.campaigns.show', 'campaign-import-errors'));
    $response->assertJsonPath('props.imports.0.unapplied_valid_count', 1)
        ->assertJsonPath('props.imports.0.invalid_count', 1)
        ->assertJsonPath('props.imports.0.validation_errors.0.row', 3)
        ->assertJsonPath('props.worksheet.rows', []);

    $this->post(route('x-change.cockpit.campaigns.imports.apply', [
        'worksheet' => 'campaign-import-errors',
        'import' => $response->json('props.imports.0.reference'),
    ]))->assertRedirect();

    expect(app(CampaignWorksheetRepository::class)
        ->findForOwner('campaign-import-errors', $owner->getMorphClass(), (string) $owner->getKey())
        ?->rows)->toHaveCount(1);
});

it('maps mobile and peso amount files to GCash direct transfer destinations by default', function () {
    $owner = actingAsTestUser();
    app(CampaignWorksheetRepository::class)->put(new CampaignWorksheetData(
        reference: 'campaign-import-wallet',
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'payroll',
        name: 'Wallet Payroll',
        fulfillmentMode: 'direct_bank_transfer',
    ));

    $this->post(route('x-change.cockpit.campaigns.imports.store', 'campaign-import-wallet'), [
        'file' => UploadedFile::fake()->createWithContent(
            'wallets.csv',
            "mobile number,amount\n09173011987,500.00\n",
        ),
    ])->assertRedirect();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.campaigns.show', 'campaign-import-wallet'))
        ->assertJsonPath('props.imports.0.unapplied_valid_count', 1)
        ->assertJsonPath('props.imports.0.preview.0.normalized.amount_minor', 50_000)
        ->assertJsonPath('props.imports.0.preview.0.normalized.beneficiary.institution', 'GCash')
        ->assertJsonPath('props.imports.0.preview.0.normalized.beneficiary.bank_code', 'GXCHPHM2XXX')
        ->assertJsonPath('props.imports.0.preview.0.normalized.beneficiary.bank_account', '09173011987');
});

it('allows source columns to be remapped and revalidates unapplied rows', function () {
    $owner = actingAsTestUser();
    app(CampaignWorksheetRepository::class)->put(new CampaignWorksheetData(
        reference: 'campaign-import-remap', ownerType: $owner->getMorphClass(), ownerId: (string) $owner->getKey(), profile: 'payroll', name: 'Remap Payroll',
    ));

    $this->post(route('x-change.cockpit.campaigns.imports.store', 'campaign-import-remap'), [
        'file' => UploadedFile::fake()->createWithContent('custom.csv', "contact,pay\n09173011987,125.50\n"),
    ])->assertRedirect();

    $staged = app(CampaignWorksheetImportRepository::class)
        ->forOwner('campaign-import-remap', $owner->getMorphClass(), (string) $owner->getKey())[0];
    expect($staged->validationErrors)->not->toBeEmpty();

    $this->patch(route('x-change.cockpit.campaigns.imports.mapping.update', [
        'worksheet' => 'campaign-import-remap',
        'import' => $staged->reference,
    ]), [
        'mapping' => ['mobile' => 'contact', 'amount' => 'pay'],
        'default_wallet' => 'GCash',
        'default_delivery_preference' => 'sms',
    ])->assertRedirect();

    $remapped = app(CampaignWorksheetImportRepository::class)
        ->findForOwner('campaign-import-remap', (string) $staged->reference, $owner->getMorphClass(), (string) $owner->getKey());
    expect($remapped?->validationErrors)->toBe([])
        ->and(data_get($remapped?->stagedRows, '0.normalized.amount_minor'))->toBe(12_550)
        ->and(data_get($remapped?->stagedRows, '0.normalized.delivery_preference'))->toBe('sms');
});

it('rejects ambiguous institution names and unsupported wallet rails', function (string $amount) {
    $owner = actingAsTestUser();
    app(CampaignWorksheetRepository::class)->put(new CampaignWorksheetData(
        reference: 'campaign-import-ambiguous-'.$amount,
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'payroll',
        name: 'Ambiguous Institution',
        fulfillmentMode: 'direct_bank_transfer',
    ));

    $reference = 'campaign-import-ambiguous-'.$amount;
    $this->post(route('x-change.cockpit.campaigns.imports.store', $reference), [
        'file' => UploadedFile::fake()->createWithContent(
            'banks.csv',
            "bank,account number,amount\nMaya,09173011987,{$amount}\n",
        ),
    ])->assertRedirect();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.campaigns.show', $reference))
        ->assertJsonPath('props.imports.0.invalid_count', 1)
        ->assertJsonPath('props.imports.0.unapplied_valid_count', 0);
})->with(['500.00', '50000.00']);

it('rejects formula cells in xlsx imports', function () {
    $owner = actingAsTestUser();
    app(CampaignWorksheetRepository::class)->put(new CampaignWorksheetData(
        reference: 'campaign-import-formula', ownerType: $owner->getMorphClass(), ownerId: (string) $owner->getKey(), profile: 'payroll', name: 'Formula Payroll',
    ));

    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['mobile', 'amount'],
        ['09173011987', '=100+25'],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'campaign-xlsx-');
    (new Xlsx($spreadsheet))->save($path);

    $this->post(route('x-change.cockpit.campaigns.imports.store', 'campaign-import-formula'), [
        'file' => new UploadedFile($path, 'formula.xlsx', null, null, true),
    ])->assertSessionHasErrors('file');
});

it('blocks worksheet freezing while valid imported rows remain unapplied', function () {
    $owner = actingAsTestUser();
    app(CampaignWorksheetRepository::class)->put(new CampaignWorksheetData(
        reference: 'campaign-import-freeze',
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'payroll',
        name: 'Freeze Guard Payroll',
        rows: [new CampaignWorksheetRowData(null, 1, ['mobile' => '09170000001'], 10_000)],
    ));

    $this->post(route('x-change.cockpit.campaigns.imports.store', 'campaign-import-freeze'), [
        'file' => UploadedFile::fake()->createWithContent('pending.csv', "mobile,amount\n09173011987,125.00\n"),
    ])->assertRedirect();

    $this->post(route('x-change.cockpit.campaigns.authorizations.store', 'campaign-import-freeze'))
        ->assertSessionHas('campaign_notice', 'Add or discard every valid staged import before freezing the worksheet.');

    expect(app(CampaignWorksheetRepository::class)
        ->findForOwner('campaign-import-freeze', $owner->getMorphClass(), (string) $owner->getKey())
        ?->status)->toBe('draft');
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
    $owner = actingAsTestUser(0);
    fundCampaignOwnerClientFunds(
        $owner,
        20_000,
        'netbank:campaign-test-client-funds',
    );
    $positions = TreasuryPosition::query()
        ->whereMorphedTo('principal', $owner)
        ->where('connection_reference', 'netbank-primary')
        ->get()
        ->keyBy('purpose');
    $clientFunds = $positions->get(
        TreasuryPositionPurpose::ClientFunds->value,
    );
    $payCodeReserve = $positions->get(
        TreasuryPositionPurpose::PayCodeReserve->value,
    );
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
        ->and($authorization->fulfillments()->whereNotNull('pay_code')->count())->toBe(2)
        ->and((int) $owner->wallet()->where('slug', 'platform')->sole()->balance)->toBe(0)
        ->and(Wallet::query()->findOrFail($clientFunds->internal_ledger_id)->getBalanceIntAttribute())->toBe(0)
        ->and(Wallet::query()->findOrFail($payCodeReserve->internal_ledger_id)->getBalanceIntAttribute())->toBe(20_000);

    $this->post(route('x-change.cockpit.campaigns.fulfillments.pay-codes.store', $worksheet->reference))
        ->assertRedirect(route('x-change.cockpit.campaigns.show', $worksheet->reference))
        ->assertSessionHas('campaign_notice', '0 beneficiary Pay Codes issued. No messages or transfers were sent.');

    expect($authorization->fulfillments()->where('status', 'issued')->count())->toBe(2)
        ->and($authorization->fulfillments()->whereNotNull('pay_code')->count())->toBe(2);
});

it('rolls back campaign Pay Code issuance when Client Funds are insufficient', function () {
    $owner = actingAsTestUser(0);
    enableNetbankTreasuryForTests();
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $owner,
        ['netbank-primary'],
    );
    $officer = actingAsTestUser();
    $officer->forceFill(['mobile' => '09173011987'])->save();
    $repository = app(CampaignWorksheetRepository::class);
    $worksheet = $repository->put(new CampaignWorksheetData(
        reference: 'campaign-cockpit-insufficient-client-funds-01',
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'payroll',
        name: 'Insufficient Client Funds Payroll',
        fulfillmentMode: 'pay_code_distribution',
        rows: [
            new CampaignWorksheetRowData(
                null,
                1,
                ['mobile' => '09178889999'],
                12_500,
            ),
        ],
    ));
    $repository->freeze(
        (string) $worksheet->reference,
        $owner->getMorphClass(),
        (string) $owner->getKey(),
    );

    $this->actingAs($owner);
    $authorization = app(
        IssueCampaignWorksheetApprovalPayCode::class,
    )->handle((string) $worksheet->reference, $owner);

    $this->actingAs($officer);
    app(CampaignWorksheetAuthorizationExecutionService::class)->execute(
        Voucher::query()
            ->where('code', $authorization->approval_pay_code)
            ->sole(),
        ['mobile' => '09173011987'],
    );
    $voucherCountBefore = Voucher::query()->count();

    $this->actingAs($owner)
        ->post(route(
            'x-change.cockpit.campaigns.fulfillments.pay-codes.store',
            $worksheet->reference,
        ))
        ->assertRedirect(route(
            'x-change.cockpit.campaigns.show',
            $worksheet->reference,
        ))
        ->assertSessionHas(
            'campaign_notice',
            'Campaign Pay Codes could not be issued because Client Funds are insufficient.',
        );

    $fulfillment = $authorization->refresh()->fulfillments()->sole();
    $positions = TreasuryPosition::query()
        ->whereMorphedTo('principal', $owner)
        ->where('connection_reference', 'netbank-primary')
        ->get()
        ->keyBy('purpose');

    expect(Voucher::query()->count())->toBe($voucherCountBefore)
        ->and($fulfillment->status)->toBe('planned')
        ->and($fulfillment->pay_code)->toBeNull()
        ->and(Wallet::query()->findOrFail(
            $positions->get(
                TreasuryPositionPurpose::ClientFunds->value,
            )->internal_ledger_id,
        )->getBalanceIntAttribute())->toBe(0)
        ->and(Wallet::query()->findOrFail(
            $positions->get(
                TreasuryPositionPurpose::PayCodeReserve->value,
            )->internal_ledger_id,
        )->getBalanceIntAttribute())->toBe(0);
});

it('records an explicit export as an immutable append-only delivery attempt', function () {
    $owner = actingAsTestUser();
    fundCampaignOwnerClientFunds(
        $owner,
        100_000,
        'netbank:campaign-explicit-export',
    );
    $officer = actingAsTestUser();
    $officer->forceFill(['mobile' => '09173011987'])->save();
    $repository = app(CampaignWorksheetRepository::class);

    $worksheet = $repository->put(new CampaignWorksheetData(
        reference: 'campaign-explicit-export-01',
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'payroll',
        name: 'Explicit Export Payroll',
        fulfillmentMode: 'pay_code_distribution',
        rows: [
            new CampaignWorksheetRowData(null, 1, [
                'name' => 'Maria Santos',
                'mobile' => '09178889999',
                'email' => 'maria@example.test',
            ], 12_500),
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
        ->assertRedirect();

    expect(CampaignDeliveryAttempt::query()->count())->toBe(0);

    $response = $this->actingAs($owner)
        ->get(route('x-change.cockpit.campaigns.exports.pay-codes', $worksheet->reference))
        ->assertOk()
        ->assertHeader('cache-control', 'no-store, private');

    $issuedPayCode = (string) $authorization->fulfillments()->value('pay_code');

    expect($response->streamedContent())
        ->toContain('Maria Santos')
        ->toContain('maria@example.test')
        ->toContain(route('x-change.claim.show', ['code' => $issuedPayCode]))
        ->not->toContain('/x/claim?'.$issuedPayCode);

    $attempt = CampaignDeliveryAttempt::query()->with('events')->sole();

    expect($attempt->channel)->toBe('export')
        ->and($attempt->campaign_worksheet_fulfillment_id)->toBeNull()
        ->and($attempt->metadata)->toMatchArray([
            'format' => 'csv',
            'export_type' => 'pay_codes',
            'record_count' => 1,
        ])
        ->and($attempt->events->pluck('event_type')->all())->toBe(['requested', 'completed'])
        ->and($this->fakeAuditLogger()->hasEvent('campaign.delivery.requested'))->toBeTrue()
        ->and($this->fakeAuditLogger()->hasEvent('campaign.delivery.completed'))->toBeTrue();

    expect(fn () => $attempt->update(['channel' => 'sms']))
        ->toThrow(LogicException::class, 'Campaign delivery attempts are append-only.')
        ->and(fn () => $attempt->events->first()->delete())
        ->toThrow(LogicException::class, 'Campaign delivery attempt events are append-only.');
});

it('queues campaign email on x-change-feedback and records blocked routes', function () {
    Mail::fake();
    Queue::fake([
        DispatchCampaignFeedbackJob::class,
    ]);
    config()->set('x-change.campaigns.delivery.email.enabled', true);
    config()->set('x-change.redemption.feedback.queue', 'x-change-feedback');
    $owner = actingAsTestUser();
    fundCampaignOwnerClientFunds(
        $owner,
        100_000,
        'netbank:campaign-explicit-email',
    );
    $officer = actingAsTestUser();
    $officer->forceFill(['mobile' => '09173011987'])->save();
    $repository = app(CampaignWorksheetRepository::class);

    $worksheet = $repository->put(new CampaignWorksheetData(
        reference: 'campaign-explicit-email-01',
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'assistance',
        name: 'Explicit Email Assistance',
        fulfillmentMode: 'pay_code_distribution',
        rows: [
            new CampaignWorksheetRowData(null, 1, ['name' => 'Maria Santos', 'email' => 'maria@example.test'], 1_000),
            new CampaignWorksheetRowData(null, 2, ['name' => 'No Email', 'mobile' => '09179998888'], 2_000),
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
        ->assertRedirect();

    expect(CampaignDeliveryAttempt::query()->count())->toBe(0);
    Mail::assertNothingSent();

    $this->post(route('x-change.cockpit.campaigns.deliveries.store', [
        'worksheet' => $worksheet->reference,
        'channel' => 'email',
    ]))
        ->assertRedirect(route('x-change.cockpit.campaigns.show', $worksheet->reference))
        ->assertSessionHas('campaign_notice', 'EMAIL delivery: 1 queued, 1 blocked, 0 already attempted.');

    Mail::assertNothingSent();
    Queue::assertPushedOn(
        'x-change-feedback',
        DispatchCampaignFeedbackJob::class,
        fn (DispatchCampaignFeedbackJob $job): bool => $job->queue === 'x-change-feedback'
            && $job->recipient === 'maria@example.test',
    );

    expect(CampaignDeliveryAttempt::query()->where('channel', 'email')->count())->toBe(2)
        ->and(CampaignDeliveryAttempt::query()
            ->with('events')
            ->get()
            ->map(fn (CampaignDeliveryAttempt $attempt): string => (string) $attempt->events->last()?->event_type)
            ->sort()
            ->values()
            ->all())
        ->toBe(['blocked', 'queued']);

    $queuedJob = null;
    Queue::assertPushed(
        DispatchCampaignFeedbackJob::class,
        function (DispatchCampaignFeedbackJob $job) use (&$queuedJob): bool {
            $queuedJob = $job;

            return $job->recipient === 'maria@example.test';
        },
    );
    expect($queuedJob)->toBeInstanceOf(DispatchCampaignFeedbackJob::class);
    $queuedJob->handle(app(DispatchCampaignFeedback::class));

    $emailFulfillment = $authorization->fulfillments()
        ->with('row')
        ->get()
        ->first(fn ($fulfillment): bool => $fulfillment->row?->ordinal === 1);
    $emailClaimUrl = route('x-change.claim.show', [
        'code' => $emailFulfillment?->pay_code,
    ]);

    Mail::assertSent(
        FeedbackEmailMessage::class,
        fn (FeedbackEmailMessage $message): bool => str_contains($message->intent->message->body, $emailClaimUrl)
            && data_get($message->intent->message->actions, '0.href') === $emailClaimUrl
            && ! str_contains($message->intent->message->body, '/x/claim?APPR-'),
    );
    expect(ExecutionJournalEntry::query()
        ->where('correlation_id', $authorization->reference)
        ->orderBy('id')
        ->pluck('event_type')
        ->all())->toBe(['feedback.created', 'feedback.sent']);

    $this->post(route('x-change.cockpit.campaigns.deliveries.store', [
        'worksheet' => $worksheet->reference,
        'channel' => 'email',
    ]))
        ->assertSessionHas('campaign_notice', 'EMAIL delivery: 0 queued, 0 blocked, 2 already attempted.');

    Mail::assertSent(FeedbackEmailMessage::class, 1);
    expect(CampaignDeliveryAttempt::query()->where('channel', 'email')->count())->toBe(2);

    $blocked = CampaignDeliveryAttempt::query()
        ->whereHas('events', fn ($query) => $query->where('event_type', 'blocked'))
        ->sole();

    $this->post(route('x-change.cockpit.campaigns.deliveries.retries.store', [
        'worksheet' => $worksheet->reference,
        'attempt' => $blocked->reference,
    ]))
        ->assertRedirect(route('x-change.cockpit.campaigns.show', $worksheet->reference))
        ->assertSessionHas('campaign_notice', 'EMAIL delivery retry finished: blocked.');

    $retry = CampaignDeliveryAttempt::query()
        ->where('retry_of_reference', $blocked->reference)
        ->with('events')
        ->sole();

    expect($retry->attempt_number)->toBe(3)
        ->and($retry->events->pluck('event_type')->all())->toBe(['requested', 'blocked'])
        ->and(CampaignDeliveryAttempt::query()->where('channel', 'email')->count())->toBe(3);
});

it('uses the canonical claim path in beneficiary Pay Code sms', function () {
    Queue::fake([
        ConvergeCampaignFeedbackDeliveryJob::class,
        DispatchCampaignFeedbackJob::class,
        DeliverQueuedFeedbackSmsJob::class,
    ]);
    config()->set('x-change.campaigns.delivery.sms.enabled', true);
    config()->set('x-change.redemption.feedback.queue', 'x-change-feedback');
    $owner = actingAsTestUser();
    fundCampaignOwnerClientFunds(
        $owner,
        100_000,
        'netbank:campaign-beneficiary-sms-claim-url',
    );
    $officer = actingAsTestUser();
    $officer->forceFill(['mobile' => '09173011987'])->save();
    $repository = app(CampaignWorksheetRepository::class);
    $worksheet = $repository->put(new CampaignWorksheetData(
        reference: 'campaign-beneficiary-sms-claim-url-01',
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'assistance',
        name: 'Beneficiary SMS Claim URL',
        fulfillmentMode: 'pay_code_distribution',
        rows: [
            new CampaignWorksheetRowData(
                null,
                1,
                ['name' => 'Maria Santos', 'mobile' => '09179998888'],
                1_000,
            ),
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
        ->assertRedirect();
    $this->post(route('x-change.cockpit.campaigns.deliveries.store', [
        'worksheet' => $worksheet->reference,
        'channel' => 'sms',
    ]))->assertSessionHas('campaign_notice', 'SMS delivery: 1 queued, 0 blocked, 0 already attempted.');

    $beneficiaryCampaignJob = null;
    Queue::assertPushed(
        DispatchCampaignFeedbackJob::class,
        function (DispatchCampaignFeedbackJob $job) use (&$beneficiaryCampaignJob): bool {
            if ($job->recipient !== '09179998888') {
                return false;
            }

            $beneficiaryCampaignJob = $job;

            return true;
        },
    );

    expect($beneficiaryCampaignJob)->toBeInstanceOf(DispatchCampaignFeedbackJob::class);
    $beneficiaryCampaignJob->handle(app(DispatchCampaignFeedback::class));

    $smsFulfillment = $authorization->fulfillments()->sole();
    $smsClaimUrl = route('x-change.claim.show', [
        'code' => $smsFulfillment->pay_code,
    ]);

    Queue::assertPushed(
        DeliverQueuedFeedbackSmsJob::class,
        fn (DeliverQueuedFeedbackSmsJob $job): bool => str_contains($job->message, $smsClaimUrl)
            && str_contains($job->message, '/x/claim/'.$smsFulfillment->pay_code)
            && ! str_contains($job->message, '/x/claim?APPR-'),
    );
});

it('rejects campaign messaging while its explicit runtime gate is disabled', function () {
    $owner = actingAsTestUser();
    $repository = app(CampaignWorksheetRepository::class);
    $worksheet = $repository->put(new CampaignWorksheetData(
        reference: 'campaign-disabled-message-01',
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'payroll',
        name: 'Disabled Messaging',
    ));

    $this->post(route('x-change.cockpit.campaigns.deliveries.store', [
        'worksheet' => $worksheet->reference,
        'channel' => 'sms',
    ]))->assertForbidden();

    expect(CampaignDeliveryAttempt::query()->count())->toBe(0);
});

it('queues an awaiting officer approval Pay Code through x-change feedback without approving the worksheet', function () {
    Mail::fake();
    Queue::fake([DispatchCampaignFeedbackJob::class]);
    config()->set('x-change.campaigns.delivery.email.enabled', true);
    config()->set('x-change.redemption.feedback.queue', 'x-change-feedback');
    $owner = actingAsTestUser();
    $repository = app(CampaignWorksheetRepository::class);
    $worksheet = $repository->put(new CampaignWorksheetData(
        reference: 'campaign-approval-email-01',
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'payroll',
        name: 'Approval Email Payroll',
        fulfillmentMode: 'pay_code_distribution',
        rows: [new CampaignWorksheetRowData(null, 1, ['mobile' => '09178889999'], 12_500)],
    ));
    $repository->freeze((string) $worksheet->reference, $owner->getMorphClass(), (string) $owner->getKey());
    $authorization = app(IssueCampaignWorksheetApprovalPayCode::class)->handle((string) $worksheet->reference, $owner);
    $requestToken = (string) Str::uuid();

    Mail::assertNothingSent();
    expect(CampaignDeliveryAttempt::query()->count())->toBe(0);

    $this->post(route('x-change.cockpit.campaigns.authorizations.deliveries.store', [
        'worksheet' => $worksheet->reference,
        'authorization' => $authorization->reference,
        'channel' => 'email',
    ]), [
        'recipient' => 'officer@example.test',
        'request_token' => $requestToken,
    ])
        ->assertRedirect(route('x-change.cockpit.campaigns.show', $worksheet->reference))
        ->assertSessionHas('campaign_notice', 'Approval Pay Code queued for EMAIL delivery.');

    Mail::assertNothingSent();
    Queue::assertPushedOn(
        'x-change-feedback',
        DispatchCampaignFeedbackJob::class,
        fn (DispatchCampaignFeedbackJob $job): bool => $job->queue === 'x-change-feedback'
            && $job->recipient === 'officer@example.test',
    );

    $attempt = CampaignDeliveryAttempt::query()->with('events')->sole();

    expect($attempt->campaign_worksheet_fulfillment_id)->toBeNull()
        ->and($attempt->channel)->toBe('email')
        ->and($attempt->recipient_route_hash)->toBe(hash('sha256', 'officer@example.test'))
        ->and($attempt->metadata)->toMatchArray([
            'purpose' => 'officer_authorization',
            'pay_code' => $authorization->approval_pay_code,
        ])
        ->and($attempt->events->pluck('event_type')->all())->toBe(['requested', 'queued'])
        ->and($authorization->fresh()->status)->toBe('awaiting_officer')
        ->and($authorization->fulfillments()->count())->toBe(0);

    $queuedJob = null;
    Queue::assertPushed(
        DispatchCampaignFeedbackJob::class,
        function (DispatchCampaignFeedbackJob $job) use (&$queuedJob): bool {
            $queuedJob = $job;

            return true;
        },
    );
    expect($queuedJob)->toBeInstanceOf(DispatchCampaignFeedbackJob::class);
    $queuedJob->handle(app(DispatchCampaignFeedback::class));

    Mail::assertSent(FeedbackEmailMessage::class, function (FeedbackEmailMessage $message) use ($authorization): bool {
        $claimUrl = route('x-change.claim.show', [
            'code' => $authorization->approval_pay_code,
        ]);

        return $message->hasTo('officer@example.test')
            && str_contains($message->intent->message->body, $claimUrl)
            && data_get($message->intent->message->actions, '0.href') === $claimUrl
            && ! str_contains($message->intent->message->body, '/x/claim?APPR-')
            && ! str_contains((string) data_get($message->intent->message->actions, '0.href'), '/x/claim?APPR-');
    });
    expect($attempt->fresh()->events->pluck('event_type')->all())
        ->toBe(['requested', 'queued', 'completed'])
        ->and(ExecutionJournalEntry::query()
            ->where('correlation_id', $authorization->reference)
            ->orderBy('id')
            ->pluck('event_type')
            ->all())
        ->toBe(['feedback.created', 'feedback.sent']);

    $this->post(route('x-change.cockpit.campaigns.authorizations.deliveries.store', [
        'worksheet' => $worksheet->reference,
        'authorization' => $authorization->reference,
        'channel' => 'email',
    ]), [
        'recipient' => 'officer@example.test',
        'request_token' => $requestToken,
    ])->assertSessionHas('campaign_notice', 'This approval delivery request was already processed.');

    Mail::assertSent(FeedbackEmailMessage::class, 1);
    expect(CampaignDeliveryAttempt::query()->count())->toBe(1);
});

it('preserves the feedback created and queued journal lifecycle for campaign sms', function () {
    Queue::fake([
        ConvergeCampaignFeedbackDeliveryJob::class,
        DispatchCampaignFeedbackJob::class,
        DeliverQueuedFeedbackSmsJob::class,
    ]);
    config()->set('x-change.campaigns.delivery.sms.enabled', true);
    config()->set('x-change.redemption.feedback.queue', 'x-change-feedback');
    config()->set('x-feedback.transports.sms.driver', 'engagespark');
    $owner = actingAsTestUser();
    $repository = app(CampaignWorksheetRepository::class);
    $worksheet = $repository->put(new CampaignWorksheetData(
        reference: 'campaign-approval-sms-01',
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'assistance',
        name: 'Approval SMS Assistance',
        rows: [new CampaignWorksheetRowData(null, 1, ['mobile' => '09178889999'], 5_000)],
    ));
    $repository->freeze((string) $worksheet->reference, $owner->getMorphClass(), (string) $owner->getKey());
    $authorization = app(IssueCampaignWorksheetApprovalPayCode::class)->handle((string) $worksheet->reference, $owner);

    $this->post(route('x-change.cockpit.campaigns.authorizations.deliveries.store', [
        'worksheet' => $worksheet->reference,
        'authorization' => $authorization->reference,
        'channel' => 'sms',
    ]), [
        'recipient' => '09173011987',
        'request_token' => (string) Str::uuid(),
    ])->assertSessionHas('campaign_notice', 'Approval Pay Code queued for SMS delivery.');

    $campaignJob = null;
    Queue::assertPushedOn(
        'x-change-feedback',
        DispatchCampaignFeedbackJob::class,
        function (DispatchCampaignFeedbackJob $job) use (&$campaignJob): bool {
            $campaignJob = $job;

            return $job->recipient === '09173011987';
        },
    );
    Queue::assertNotPushed(DeliverQueuedFeedbackSmsJob::class);

    expect($campaignJob)->toBeInstanceOf(DispatchCampaignFeedbackJob::class);
    $campaignJob->handle(app(DispatchCampaignFeedback::class));

    $providerJob = null;
    Queue::assertPushedOn(
        'x-change-feedback',
        DeliverQueuedFeedbackSmsJob::class,
        function (DeliverQueuedFeedbackSmsJob $job) use (&$providerJob, $authorization): bool {
            $providerJob = $job;
            $claimUrl = route('x-change.claim.show', [
                'code' => $authorization->approval_pay_code,
            ]);

            return $job->queue === 'x-change-feedback'
                && str_contains($job->message, $claimUrl)
                && ! str_contains($job->message, '/x/claim?APPR-');
        },
    );
    $convergenceJob = null;
    Queue::assertPushedOn(
        'x-change-feedback',
        ConvergeCampaignFeedbackDeliveryJob::class,
        function (ConvergeCampaignFeedbackDeliveryJob $job) use (&$convergenceJob): bool {
            $convergenceJob = $job;

            return $job->queue === 'x-change-feedback';
        },
    );

    $attempt = CampaignDeliveryAttempt::query()->with('events')->sole();
    $feedbackRecord = FeedbackDeliveryRecord::query()->sole();

    expect($attempt->events->pluck('event_type')->all())
        ->toBe(['requested', 'queued', 'provider_queued'])
        ->and($providerJob)->toBeInstanceOf(DeliverQueuedFeedbackSmsJob::class)
        ->and(ExecutionJournalEntry::query()
            ->where('correlation_id', $authorization->reference)
            ->orderBy('id')
            ->pluck('event_type')
            ->all())
        ->toBe(['feedback.created', 'feedback.queued'])
        ->and($authorization->fresh()->status)->toBe('awaiting_officer');

    $feedbackRecord->forceFill([
        'status' => 'sent',
        'provider_status' => 'ACCEPTED',
        'provider_message_id' => 'sms-provider-message-1',
    ])->save();

    expect($convergenceJob)->toBeInstanceOf(ConvergeCampaignFeedbackDeliveryJob::class);
    $convergenceJob->handle(app(ConvergeCampaignFeedbackDelivery::class));
    $convergenceJob->handle(app(ConvergeCampaignFeedbackDelivery::class));

    expect($attempt->fresh()->events->pluck('event_type')->all())
        ->toBe(['requested', 'queued', 'provider_queued', 'completed'])
        ->and($attempt->fresh()->events->last()?->provider_status)->toBe('ACCEPTED')
        ->and($attempt->fresh()->events->last()?->provider_delivery_reference)
        ->toBe('sms-provider-message-1');
});

it('converges a final x-feedback sms failure once', function () {
    Queue::fake([
        ConvergeCampaignFeedbackDeliveryJob::class,
        DispatchCampaignFeedbackJob::class,
        DeliverQueuedFeedbackSmsJob::class,
    ]);
    config()->set('x-change.campaigns.delivery.sms.enabled', true);
    config()->set('x-change.redemption.feedback.queue', 'x-change-feedback');
    $owner = actingAsTestUser();
    $repository = app(CampaignWorksheetRepository::class);
    $worksheet = $repository->put(new CampaignWorksheetData(
        reference: 'campaign-approval-sms-failure-convergence-01',
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'assistance',
        name: 'SMS Failure Convergence',
        rows: [new CampaignWorksheetRowData(null, 1, ['mobile' => '09178889999'], 5_000)],
    ));
    $repository->freeze((string) $worksheet->reference, $owner->getMorphClass(), (string) $owner->getKey());
    $authorization = app(IssueCampaignWorksheetApprovalPayCode::class)->handle((string) $worksheet->reference, $owner);

    $this->post(route('x-change.cockpit.campaigns.authorizations.deliveries.store', [
        'worksheet' => $worksheet->reference,
        'authorization' => $authorization->reference,
        'channel' => 'sms',
    ]), [
        'recipient' => '09173011987',
        'request_token' => (string) Str::uuid(),
    ])->assertSessionHas('campaign_notice', 'Approval Pay Code queued for SMS delivery.');

    $campaignJob = null;
    Queue::assertPushed(
        DispatchCampaignFeedbackJob::class,
        function (DispatchCampaignFeedbackJob $job) use (&$campaignJob): bool {
            $campaignJob = $job;

            return true;
        },
    );

    expect($campaignJob)->toBeInstanceOf(DispatchCampaignFeedbackJob::class);
    $campaignJob->handle(app(DispatchCampaignFeedback::class));

    $convergenceJob = null;
    Queue::assertPushed(
        ConvergeCampaignFeedbackDeliveryJob::class,
        function (ConvergeCampaignFeedbackDeliveryJob $job) use (&$convergenceJob): bool {
            $convergenceJob = $job;

            return true;
        },
    );

    FeedbackDeliveryRecord::query()->sole()->forceFill([
        'status' => 'failed_final',
        'provider_status' => 'FAILED',
    ])->save();

    expect($convergenceJob)->toBeInstanceOf(ConvergeCampaignFeedbackDeliveryJob::class);
    $convergenceJob->handle(app(ConvergeCampaignFeedbackDelivery::class));
    $convergenceJob->handle(app(ConvergeCampaignFeedbackDelivery::class));

    $attempt = CampaignDeliveryAttempt::query()->with('events')->sole();

    expect($attempt->events->pluck('event_type')->all())
        ->toBe(['requested', 'queued', 'provider_queued', 'failed'])
        ->and($attempt->events->last()?->provider_status)->toBe('FAILED')
        ->and($attempt->events->last()?->safe_error_code)
        ->toBe('feedback_delivery_failed_final')
        ->and($authorization->fresh()->status)->toBe('awaiting_officer');
});

it('fails closed before feedback when the campaign sms adapter can send directly', function () {
    Queue::fake([
        DispatchCampaignFeedbackJob::class,
        DeliverQueuedFeedbackSmsJob::class,
    ]);
    config()->set('x-change.campaigns.delivery.sms.enabled', true);
    config()->set('x-change.redemption.feedback.queue', 'x-change-feedback');
    $owner = actingAsTestUser();
    $repository = app(CampaignWorksheetRepository::class);
    $worksheet = $repository->put(new CampaignWorksheetData(
        reference: 'campaign-approval-sms-direct-driver-01',
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'assistance',
        name: 'Direct Driver Must Fail Closed',
        rows: [new CampaignWorksheetRowData(null, 1, ['mobile' => '09178889999'], 5_000)],
    ));
    $repository->freeze((string) $worksheet->reference, $owner->getMorphClass(), (string) $owner->getKey());
    $authorization = app(IssueCampaignWorksheetApprovalPayCode::class)->handle((string) $worksheet->reference, $owner);

    $this->post(route('x-change.cockpit.campaigns.authorizations.deliveries.store', [
        'worksheet' => $worksheet->reference,
        'authorization' => $authorization->reference,
        'channel' => 'sms',
    ]), [
        'recipient' => '09173011987',
        'request_token' => (string) Str::uuid(),
    ])->assertSessionHas('campaign_notice', 'Approval Pay Code queued for SMS delivery.');

    $campaignJob = null;
    Queue::assertPushed(
        DispatchCampaignFeedbackJob::class,
        function (DispatchCampaignFeedbackJob $job) use (&$campaignJob): bool {
            $campaignJob = $job;

            return true;
        },
    );

    app(FeedbackChannelRegistryContract::class)->register(
        'sms',
        SmsFeedbackChannelDriver::class,
    );

    expect($campaignJob)->toBeInstanceOf(DispatchCampaignFeedbackJob::class);
    $campaignJob->handle(app(DispatchCampaignFeedback::class));

    Queue::assertNotPushed(DeliverQueuedFeedbackSmsJob::class);

    $attempt = CampaignDeliveryAttempt::query()->with('events')->sole();

    expect($attempt->events->pluck('event_type')->all())
        ->toBe(['requested', 'queued', 'failed'])
        ->and($attempt->events->last()?->safe_error_code)
        ->toBe('campaign_sms_queue_boundary_unavailable')
        ->and(FeedbackDeliveryRecord::query()->count())->toBe(0)
        ->and(ExecutionJournalEntry::query()
            ->where('correlation_id', $authorization->reference)
            ->count())->toBe(0)
        ->and($authorization->fresh()->status)->toBe('awaiting_officer');
});

it('validates and gates officer approval delivery independently from authorization', function () {
    $owner = actingAsTestUser();
    $repository = app(CampaignWorksheetRepository::class);
    $worksheet = $repository->put(new CampaignWorksheetData(
        reference: 'campaign-approval-gated-01',
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'assistance',
        name: 'Gated Approval',
        rows: [new CampaignWorksheetRowData(null, 1, ['mobile' => '09178889999'], 1_000)],
    ));
    $repository->freeze((string) $worksheet->reference, $owner->getMorphClass(), (string) $owner->getKey());
    $authorization = app(IssueCampaignWorksheetApprovalPayCode::class)->handle((string) $worksheet->reference, $owner);

    $this->post(route('x-change.cockpit.campaigns.authorizations.deliveries.store', [
        'worksheet' => $worksheet->reference,
        'authorization' => $authorization->reference,
        'channel' => 'email',
    ]), [
        'recipient' => 'officer@example.test',
        'request_token' => (string) Str::uuid(),
    ])->assertForbidden();

    config()->set('x-change.campaigns.delivery.email.enabled', true);

    $this->from(route('x-change.cockpit.campaigns.show', $worksheet->reference))
        ->post(route('x-change.cockpit.campaigns.authorizations.deliveries.store', [
            'worksheet' => $worksheet->reference,
            'authorization' => $authorization->reference,
            'channel' => 'email',
        ]), [
            'recipient' => 'not-an-email',
            'request_token' => (string) Str::uuid(),
        ])
        ->assertRedirect(route('x-change.cockpit.campaigns.show', $worksheet->reference))
        ->assertSessionHasErrors('recipient');

    expect(CampaignDeliveryAttempt::query()->count())->toBe(0)
        ->and($authorization->fresh()->status)->toBe('awaiting_officer');
});
