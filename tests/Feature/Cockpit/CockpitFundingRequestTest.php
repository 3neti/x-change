<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use LBHurtado\SettlementEnvelope\Models\EnvelopeAttachment;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Funding\CreateFundingRequest;
use LBHurtado\XChange\Data\Funding\CreateFundingRequestData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Events\FundingRequestChanged;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Models\FundingRequestNotice;
use LBHurtado\XChange\Services\Claim\VoucherClaimantReference;
use LBHurtado\XChange\Services\Cockpit\FundingRequestCockpitReadModel;
use LBHurtado\XChange\Tests\Fakes\User;

it('creates a locked requester-owned Pay Code from an amount-only request', function () {
    $requester = actingAsTestUser(0);
    $reviewer = User::query()->create([
        'name' => 'Funding Reviewer',
        'email' => 'funding-reviewer@example.test',
        'password' => 'password',
    ]);
    config()->set('x-change.funding.requests.reviewer_ids', [
        (string) $reviewer->getKey(),
    ]);
    Event::fake([FundingRequestChanged::class]);

    $this->post(route('x-change.cockpit.funding.requests.store'), [
        'requested_value_minor' => '1750',
        'requester_notes' => 'Cash was handed to the system owner.',
        'idempotency_key' => 'cockpit-amount-only-funding-request-1001',
    ])->assertRedirect(route('x-change.cockpit.funding.index', [
        'mode' => 'pay_code',
    ]))
        ->assertSessionHas(
            'funding_notice',
            'Funding requested. Share the Pay Code if you want to follow up.',
        )
        ->assertSessionHas('funding_request_submitted_reference');

    $request = FundingRequest::query()
        ->with('voucher.envelope')
        ->sole();
    $readModel = app(FundingRequestCockpitReadModel::class)
        ->forOperator($requester);

    expect($request->funding_type)->toBe(FundingRequestType::Unspecified)
        ->and($request->requested_value_minor)->toBe(1_750)
        ->and($request->currency)->toBe('PHP')
        ->and($request->description)->toBe(
            'Account funding requested by the Account holder.',
        )
        ->and($request->requester_notes_ciphertext)->toBe(
            'Cash was handed to the system owner.',
        )
        ->and($request->status)->toBe(FundingRequestStatus::Submitted)
        ->and($request->voucher)->not->toBeNull()
        ->and($request->voucher->owner->is($requester))->toBeTrue()
        ->and($request->voucher->state->value)->toBe('locked')
        ->and(data_get(
            $request->voucher->metadata,
            'instructions.target_amount',
        ))->toBe(17.5)
        ->and($request->voucher->envelope)->not->toBeNull()
        ->and(data_get($readModel, 'requests.0.receipt_status'))->toBe('pending')
        ->and(data_get($readModel, 'requests.0.receipt_status_label'))->toBe('Pending')
        ->and(data_get($readModel, 'requests.0.pay_code.status'))
        ->toBe('locked_pending_review')
        ->and(data_get($readModel, 'requests.0.pay_code.code'))
        ->toBe($request->voucher->code)
        ->and(data_get($readModel, 'requests.0.pay_code.can_copy'))->toBeTrue()
        ->and(FundingRequestNotice::query()
            ->where('recipient_id', (string) $reviewer->getKey())
            ->where('notice_type', 'funding_request_submitted')
            ->count())->toBe(1);

    Event::assertDispatched(
        FundingRequestChanged::class,
        function (FundingRequestChanged $event) use ($request): bool {
            $payload = $event->broadcastWith();

            return $payload['request_reference'] === $request->reference
                && $payload['status'] === 'submitted'
                && ! str_contains(
                    json_encode($payload, JSON_THROW_ON_ERROR),
                    $request->voucher->code,
                )
                && ! array_key_exists('message', $payload)
                && ! array_key_exists('account_reference', $payload);
        },
    );
});

it('lets an Account owner submit a request without accepting monetary authority', function () {
    Storage::fake('local');
    $requester = actingAsTestUser(25_000);
    $balanceBefore = (int) $requester->wallet->balance;

    $this->post(route('x-change.cockpit.funding.requests.store'), [
        'funding_type' => 'BANK_TRANSFER',
        'requested_value_minor' => '2000000',
        'currency' => 'php',
        'description' => 'Large corporate transfer awaiting an authoritative bank match.',
        'external_reference' => 'BANK-2026-1001',
        'occurred_on' => '2026-07-25',
        'requester_notes' => 'Please verify against the configured corporate account.',
        'idempotency_key' => 'cockpit-funding-request-1001',
        'evidence_document_type' => 'BANK_TRANSFER_PROOF',
        'evidence_document' => UploadedFile::fake()->image(
            'transfer-proof.jpg',
            1200,
            900,
        ),
    ])->assertRedirect(route('x-change.cockpit.funding.index', [
        'mode' => 'pay_code',
    ]))
        ->assertSessionHas('funding_notice');

    $request = FundingRequest::query()->sole();

    expect($request->status)->toBe(FundingRequestStatus::Submitted)
        ->and($request->approved_value_minor)->toBeNull()
        ->and($request->voucher?->envelope?->attachments()->count())->toBe(1)
        ->and((int) $requester->wallet->fresh()->balance)->toBe($balanceBefore);

    $attachment = EnvelopeAttachment::query()->sole();

    expect($attachment->disk)->toBe('local')
        ->and($attachment->doc_type)->toBe('BANK_TRANSFER_PROOF')
        ->and($attachment->hash)->toHaveLength(64)
        ->and($attachment->review_status)->toBe('pending');
    Storage::disk('local')->assertExists($attachment->file_path);
    $this->get(route(
        'x-change.cockpit.funding.requests.evidence.show',
        [$request, $attachment],
    ))->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->post(route('x-change.cockpit.funding.requests.store'), [
        'funding_type' => 'bank_transfer',
        'requested_value_minor' => 2_000_000,
        'currency' => 'PHP',
        'description' => 'Large corporate transfer awaiting an authoritative bank match.',
        'idempotency_key' => 'cockpit-funding-request-forged-1001',
        'approved_value_minor' => 2_000_000,
        'provider_transaction_id' => 'forged',
    ])->assertSessionHasErrors(['approved_value_minor', 'provider_transaction_id']);

    expect(FundingRequest::query()->count())->toBe(1);
});

it('keeps privileged funding controls out of the requester workspace', function () {
    $requester = actingAsTestUser();
    $request = app(CreateFundingRequest::class)->handle(new CreateFundingRequestData(
        accountReference: 'wallet:'.$requester->wallet->uuid,
        requesterType: $requester::class,
        requesterId: (string) $requester->getKey(),
        fundingType: FundingRequestType::CashHandover,
        requestedValueMinor: 100_000,
        currency: 'PHP',
        description: 'Cash custody request requiring independent review.',
        idempotencyKey: 'cockpit-review-queue-1001',
    ));
    $unrelated = actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.funding.index'))
        ->assertOk()
        ->assertJsonCount(0, 'props.funding_requests.review_queue')
        ->assertJsonPath('props.funding_requests.controls.reviewer', false);

    $this->post(route(
        'x-change.cockpit.funding.requests.reviews.store',
        $request,
    ), [
        'recognized_value_minor' => 100_000,
        'currency' => 'PHP',
        'connection_reference' => 'netbank-primary',
        'evidence_reference' => 'custody:1001',
    ])->assertForbidden();

    config()->set('x-change.funding.requests.maker_ids', [
        (string) $unrelated->getKey(),
    ]);
    config()->set('x-change.funding.requests.checker_ids', [
        (string) $unrelated->getKey(),
    ]);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.funding.index'))
        ->assertOk()
        ->assertJsonCount(0, 'props.funding_requests.review_queue')
        ->assertJsonPath('props.funding_requests.controls.reviewer', false);
});

it('binds Reviewed Funding Pay Code claims to the intended Account owner', function () {
    $requester = actingAsTestUser();
    $other = User::query()->create([
        'name' => 'Other Funding User',
        'email' => 'other-funding-user@example.test',
        'password' => 'password',
    ]);
    fundTestUserWallet($other, 0);
    $voucher = Voucher::query()->forceCreate([
        'code' => 'OWNER-BOUND',
        'metadata' => [
            'instructions' => [
                'cash' => [
                    'amount' => 10,
                    'currency' => 'PHP',
                    'validation' => ['country' => 'PH'],
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
                'count' => 1,
                'prefix' => 'OWNER',
                'mask' => '****',
                'claim' => [
                    'outcomes' => [['key' => 'account_funding']],
                    'selection' => 'server',
                    'consumption' => 'one_of',
                    'default_outcome' => 'account_funding',
                    'claimant' => [
                        'mode' => 'recipient',
                        'reference' => app(VoucherClaimantReference::class)
                            ->for($requester),
                    ],
                ],
            ],
        ],
        'voucher_type' => 'redeemable',
        'state' => 'active',
        'expires_at' => now()->addHour(),
    ]);
    $request = FundingRequest::query()->create([
        'voucher_id' => $voucher->getKey(),
        'account_reference' => 'wallet:'.$requester->wallet->uuid,
        'requester_type' => $requester::class,
        'requester_id' => (string) $requester->getKey(),
        'funding_type' => FundingRequestType::BankTransfer,
        'requested_value_minor' => 1_000,
        'approved_value_minor' => 1_000,
        'currency' => 'PHP',
        'status' => FundingRequestStatus::PayCodeIssued,
        'version' => 3,
        'idempotency_key_hash' => hash('sha256', 'claim-owner-test'),
        'idempotency_fingerprint' => hash('sha256', 'claim-owner-fingerprint'),
        'description' => 'Owner binding test request.',
        'submitted_at' => now(),
    ]);
    $readModel = app(FundingRequestCockpitReadModel::class)
        ->forOperator($requester);

    expect(data_get($readModel, 'requests.0.status'))->toBe('pay_code_issued')
        ->and(data_get($readModel, 'requests.0.pay_code.code'))
        ->toBe('OWNER-BOUND')
        ->and(data_get(
            $readModel,
            'redactions.reviewed_pay_code_exposed_to_owner',
        ))->toBeTrue();

    $this->actingAs($other)
        ->post(route(
            'x-change.cockpit.funding.requests.pay-code-claims.store',
            $request,
        ))
        ->assertForbidden();

    expect($voucher->refresh()->redeemed_at)->toBeNull();
});
