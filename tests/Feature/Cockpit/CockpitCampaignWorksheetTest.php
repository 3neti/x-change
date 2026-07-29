<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;
use LBHurtado\XCampaign\Data\CampaignWorksheetData;
use LBHurtado\XCampaign\Data\CampaignWorksheetRowData;
use LBHurtado\XChange\Actions\Campaigns\IssueCampaignWorksheetApprovalPayCode;
use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use LBHurtado\XChange\Services\Campaigns\CampaignWorksheetAuthorizationExecutionService;
use LBHurtado\XFeedback\Mail\FeedbackEmailMessage;

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
        ->assertJsonPath('props.worksheet.rows.0.amount_minor', 12_500)
        ->assertJsonPath('props.direct_bank_transfer_enabled', false)
        ->assertJsonPath('props.delivery.channels.sms', false)
        ->assertJsonPath('props.delivery.channels.email', false);

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

it('records an explicit export as an immutable append-only delivery attempt', function () {
    $owner = actingAsTestUser();
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

    expect($response->streamedContent())
        ->toContain('Maria Santos')
        ->toContain('maria@example.test');

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

it('sends campaign email only through an enabled explicit action and records blocked routes', function () {
    Mail::fake();
    config()->set('x-change.campaigns.delivery.email.enabled', true);
    $owner = actingAsTestUser();
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
        ->assertSessionHas('campaign_notice', 'EMAIL delivery: 1 sent, 0 failed, 1 blocked, 0 already attempted.');

    Mail::assertSent(FeedbackEmailMessage::class, 1);

    expect(CampaignDeliveryAttempt::query()->where('channel', 'email')->count())->toBe(2)
        ->and(CampaignDeliveryAttempt::query()
            ->with('events')
            ->get()
            ->map(fn (CampaignDeliveryAttempt $attempt): string => (string) $attempt->events->last()?->event_type)
            ->sort()
            ->values()
            ->all())
        ->toBe(['blocked', 'completed']);

    $this->post(route('x-change.cockpit.campaigns.deliveries.store', [
        'worksheet' => $worksheet->reference,
        'channel' => 'email',
    ]))
        ->assertSessionHas('campaign_notice', 'EMAIL delivery: 0 sent, 0 failed, 0 blocked, 2 already attempted.');

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
