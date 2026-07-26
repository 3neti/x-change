<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use LBHurtado\SettlementEnvelope\Enums\EnvelopeStatus;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Actions\Funding\ApproveFundingRequestAndIssueCode;
use LBHurtado\XChange\Actions\Funding\AttachFundingRequestEvidence;
use LBHurtado\XChange\Actions\Funding\CreateFundingRequest;
use LBHurtado\XChange\Actions\Funding\PayApprovedFundingRequest;
use LBHurtado\XChange\Actions\Funding\PrepareFundingRequest;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Funding\CreateFundingRequestData;
use LBHurtado\XChange\Data\Funding\PrepareFundingRequestData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Events\FundingProjectionChanged;
use LBHurtado\XChange\Events\FundingRequestChanged;
use LBHurtado\XChange\Jobs\Funding\PayApprovedFundingRequestJob;
use LBHurtado\XChange\Models\FundingRequestNotice;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Cockpit\FundingRequestCockpitReadModel;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

it('requires independent approval then pays the requester-owned PAYABLE once from system Treasury', function () {
    Event::fake([
        FundingProjectionChanged::class,
        FundingRequestChanged::class,
    ]);
    Storage::fake('local');
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    $requester = actingAsTestUser(0);
    $maker = User::query()->create([
        'name' => 'Funding Maker',
        'email' => 'funding-maker@example.test',
        'password' => 'password',
    ]);
    $checker = User::query()->create([
        'name' => 'Funding Checker',
        'email' => 'funding-checker@example.test',
        'password' => 'password',
    ]);

    fundTestSystemAccountFundingReserve(
        $system,
        1_000_000,
        'reviewed-funding-1001',
    );
    $request = app(CreateFundingRequest::class)->handle(
        new CreateFundingRequestData(
            accountReference: 'wallet:'.$requester->wallet->uuid,
            requesterType: $requester::class,
            requesterId: (string) $requester->getKey(),
            fundingType: FundingRequestType::CashHandover,
            requestedValueMinor: 250_000,
            currency: 'PHP',
            description: 'Cash accepted into controlled custody.',
            idempotencyKey: 'reviewed-funding-pay-code-lifecycle-1001',
        ),
    );

    expect($request->voucher)->toBeInstanceOf(Voucher::class)
        ->and($request->voucher->owner->is($requester))->toBeTrue()
        ->and($request->voucher->voucher_type)->toBe(VoucherType::PAYABLE)
        ->and($request->voucher->state)->toBe(VoucherState::LOCKED);
    $attachment = app(AttachFundingRequestEvidence::class)->handle(
        fundingRequest: $request,
        file: UploadedFile::fake()->create(
            'custody-receipt.pdf',
            100,
            'application/pdf',
        ),
        documentType: 'CUSTODY_RECEIPT',
        actor: $requester,
    );

    $prepared = app(PrepareFundingRequest::class)->handle(
        $request,
        new PrepareFundingRequestData(
            recognizedValueMinor: 250_000,
            currency: 'PHP',
            connectionReference: 'netbank-primary',
            evidenceReference: 'custody-receipt:1001',
            reviewerType: $maker::class,
            reviewerId: (string) $maker->getKey(),
            reviewNotes: 'Custody and liquid backing independently verified.',
        ),
    );

    expect($request->voucher->envelope->refresh()->getSignalBool('backing_verified'))
        ->toBeTrue()
        ->and($attachment->refresh()->review_status)->toBe('accepted')
        ->and($attachment->reviewer_id)->toBe($maker->getKey());
    expect(fn () => app(ApproveFundingRequestAndIssueCode::class)->handle(
        $prepared,
        $maker::class,
        (string) $maker->getKey(),
    ))->toThrow(RuntimeException::class, 'backing reviewer cannot approve');

    config()->set('x-change.funding.requests.reviewer_ids', [
        (string) $maker->getKey(),
        (string) $checker->getKey(),
    ]);
    Cache::put(
        'envelope_driver:account-funding-review:1.0.0',
        'stale serialized driver',
        3600,
    );
    Queue::fake();
    $approvalResponse = $this->actingAs($checker)->post(route(
        'x-change.cockpit.funding.requests.approvals.store',
        ['fundingRequest' => $prepared->reference],
    ));

    $approvalResponse
        ->assertRedirect(route('x-change.cockpit.funding.index', [
            'mode' => 'pay_code',
        ]))
        ->assertSessionHas(
            'funding_notice',
            'Account Funding accepted. System Treasury payment was queued.',
        );
    Queue::assertPushed(
        PayApprovedFundingRequestJob::class,
        fn (PayApprovedFundingRequestJob $job): bool => $job->fundingRequestReference
            === $prepared->reference,
    );
    Queue::assertPushedOn(
        'x-change-funding',
        PayApprovedFundingRequestJob::class,
    );

    $voucher = $request->voucher->refresh();
    $approvalReplay = app(ApproveFundingRequestAndIssueCode::class)->handle(
        $prepared,
        $checker::class,
        (string) $checker->getKey(),
    );

    expect($voucher->is($request->voucher))->toBeTrue()
        ->and($approvalReplay->is($voucher))->toBeTrue()
        ->and($voucher->state)->toBe(VoucherState::ACTIVE)
        ->and(data_get(
            $voucher->metadata,
            'instructions.rules.auto_close_on_full_payment',
        ))->toBeTrue()
        ->and($voucher->envelope->status)->toBe(EnvelopeStatus::LOCKED)
        ->and($request->refresh()->status)->toBe(FundingRequestStatus::PayCodeIssued)
        ->and(FundingRequestNotice::query()->count())->toBe(1)
        ->and(positionBalance($system, TreasuryPositionPurpose::AccountFundingReserve))
        ->toBe(750_000)
        ->and(positionBalance($system, TreasuryPositionPurpose::PayCodeReserve))
        ->toBe(250_000)
        ->and(positionBalance($requester, TreasuryPositionPurpose::ClientFunds))
        ->toBe(0);

    $cockpitReadModel = app(FundingRequestCockpitReadModel::class)
        ->forOperator($requester);

    expect(data_get($cockpitReadModel, 'requests.0.pay_code.amount'))
        ->toBe('₱2,500.00')
        ->and(data_get($cockpitReadModel, 'requests.0.pay_code.status'))
        ->toBe('awaiting_system_treasury')
        ->and(data_get($cockpitReadModel, 'requests.0.pay_code.voucher_type'))
        ->toBe('payable')
        ->and(data_get($cockpitReadModel, 'requests.0.pay_code.collection_mode'))
        ->toBe('system_treasury')
        ->and(data_get($cockpitReadModel, 'requests.0.pay_code.can_claim'))
        ->toBeFalse();

    $job = new PayApprovedFundingRequestJob($prepared->reference);
    $job->failed(new RuntimeException(
        'provider account 123456 and credential must remain private',
    ));
    $failureEvent = $request->events()
        ->where('event_type', 'reviewed_funding_payment_failed')
        ->sole();

    expect($request->refresh()->status)->toBe(FundingRequestStatus::PayCodeIssued)
        ->and($failureEvent->metadata)->toMatchArray([
            'retryable' => true,
            'failure_class' => RuntimeException::class,
        ])
        ->and(json_encode($failureEvent->metadata))->not->toContain('123456')
        ->and(FundingRequestNotice::query()
            ->where('notice_type', 'reviewed_funding_payment_retry_required')
            ->count())->toBe(1);

    $job->handle(app(PayApprovedFundingRequest::class));
    $job->handle(app(PayApprovedFundingRequest::class));
    $collection = VoucherCollection::query()->sole();
    $paymentReplay = app(PayApprovedFundingRequest::class)->handle($voucher);

    expect($collection)->toBeInstanceOf(VoucherCollection::class)
        ->and($paymentReplay->is($collection))->toBeTrue()
        ->and($job->uniqueId())->toBe(
            'reviewed-funding-payment:'.$prepared->reference,
        )
        ->and($collection->execution_driver)->toBe('x_change_account_funding')
        ->and($collection->treasury_operation_reference)->not->toBeNull()
        ->and(VoucherCollection::query()->count())->toBe(1)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'account_funding.pay_code.paid')
            ->count())->toBe(1)
        ->and($request->refresh()->status)->toBe(FundingRequestStatus::Completed)
        ->and($voucher->refresh()->state)->toBe(VoucherState::CLOSED)
        ->and($voucher->envelope->refresh()->status)->toBe(EnvelopeStatus::SETTLED)
        ->and(positionBalance($system, TreasuryPositionPurpose::AccountFundingReserve))
        ->toBe(750_000)
        ->and(positionBalance($system, TreasuryPositionPurpose::PayCodeReserve))
        ->toBe(0)
        ->and(positionBalance($requester, TreasuryPositionPurpose::ClientFunds))
        ->toBe(250_000);

    fakePayoutProvider()->assertNoDisbursementAttempted();
    Event::assertDispatchedTimes(FundingProjectionChanged::class, 1);
});

it('does not allow system Treasury payment before maker-checker approval', function () {
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    $requester = actingAsTestUser(0);

    fundTestSystemAccountFundingReserve(
        $system,
        50_000,
        'reviewed-funding-1002',
    );
    $request = app(CreateFundingRequest::class)->handle(
        new CreateFundingRequestData(
            accountReference: 'wallet:'.$requester->wallet->uuid,
            requesterType: $requester::class,
            requesterId: (string) $requester->getKey(),
            fundingType: FundingRequestType::BankTransfer,
            requestedValueMinor: 50_000,
            currency: 'PHP',
            description: 'Matched bank transfer.',
            idempotencyKey: 'reviewed-funding-pay-code-lifecycle-1002',
        ),
    );

    expect(fn () => app(PayApprovedFundingRequest::class)->handle(
        $request->voucher,
    ))->toThrow(
        RuntimeException::class,
        'not ready for system Treasury payment',
    );

    expect(positionBalance(
        $requester,
        TreasuryPositionPurpose::ClientFunds,
    ))->toBe(0);
});

function positionBalance(User $owner, TreasuryPositionPurpose $purpose): int
{
    $principal = app(
        TreasuryPrincipalReferenceResolverContract::class,
    )->resolve($owner);

    return collect(
        app(TreasuryPositionReadModelContract::class)->forPrincipal($principal),
    )
        ->first(
            fn ($position): bool => $position->purpose === $purpose,
        )
        ?->balanceMinor ?? 0;
}
