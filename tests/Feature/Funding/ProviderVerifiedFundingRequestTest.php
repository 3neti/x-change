<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use LBHurtado\EmiCore\Actions\Funding\RecordProviderFundingObservation;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\XChange\Actions\Funding\ApproveFundingRequestAndIssueCode;
use LBHurtado\XChange\Actions\Funding\CheckFundingRequestTransfer;
use LBHurtado\XChange\Actions\Funding\CreateFundingRequest;
use LBHurtado\XChange\Actions\Funding\PayApprovedFundingRequest;
use LBHurtado\XChange\Data\Funding\CreateFundingRequestData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Enums\FundingTransferWindow;
use LBHurtado\XChange\Events\FundingProjectionChanged;
use LBHurtado\XChange\Events\FundingRequestChanged;
use LBHurtado\XChange\Models\FundingRequestTransferMatch;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;
use LBHurtado\XChange\Tests\Fakes\User;

it('queries the provider history adapter before matching and crediting', function () {
    $this->travelTo(new DateTimeImmutable('2026-07-27T21:15:00+08:00'));
    enableNetbankTreasuryForTests();
    $requester = actingAsTestUser(0);
    config()->set(
        'x-change.funding.standing_addresses.creditable_provider_statuses',
        ['settled'],
    );
    $adapter = new FakeFundingProviderAdapter;
    $adapter->fundingObservation = new ProviderFundingObservationData(
        provider: 'netbank',
        providerTransactionId: 'NETBANK-LIVE-HISTORY-1737',
        grossAmountMinor: 1_737,
        feeAmountMinor: 0,
        netAmountMinor: 1_737,
        currency: 'PHP',
        providerStatus: 'settled',
        verificationSource: 'netbank-corporate-transaction-history',
        payloadHash: hash('sha256', 'NETBANK-LIVE-HISTORY-1737'),
        occurredAt: new DateTimeImmutable('2026-07-27T21:12:00+08:00'),
        settledAt: new DateTimeImmutable('2026-07-27T21:12:00+08:00'),
        metadata: [
            'destination_verified' => true,
        ],
    );
    $this->app->instance(FakeFundingProviderAdapter::class, $adapter);
    $this->app->tag(
        FakeFundingProviderAdapter::class,
        'emi.funding-provider-adapters',
    );
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
    $request = app(CreateFundingRequest::class)->handle(
        new CreateFundingRequestData(
            accountReference: 'wallet:'.$requester->wallet->uuid,
            requesterType: $requester::class,
            requesterId: (string) $requester->getKey(),
            fundingType: FundingRequestType::BankTransfer,
            requestedValueMinor: 1_737,
            currency: 'PHP',
            description: 'Live provider history lookup.',
            idempotencyKey: 'provider-history-lookup-1737',
            transferWindow: FundingTransferWindow::Recent,
        ),
    );

    $result = app(CheckFundingRequestTransfer::class)->handle($request);

    expect($result->status)->toBe('credited')
        ->and($adapter->lastVerification?->fundingAddress)
        ->toBe('113001000019')
        ->and($adapter->lastVerification?->expectedAmountMinor)->toBe(1_737)
        ->and($adapter->lastVerification?->destination?->bankAccountNumber)
        ->toBe('113001000019')
        ->and(ProviderFundingObservation::query()->sole()->verification_source)
        ->toBe('netbank-corporate-transaction-history')
        ->and((int) treasuryClientFundsLedger($requester)->balance)
        ->toBe(1_737);

    $this->travelBack();
});

it('credits one recent exact amount and time match without trusting the sender reference', function () {
    $this->travelTo(new DateTimeImmutable('2026-07-27T21:15:00+08:00'));
    Event::fake([
        FundingProjectionChanged::class,
        FundingRequestChanged::class,
    ]);
    enableNetbankTreasuryForTests();
    $requester = actingAsTestUser(0);
    config()->set(
        'x-change.funding.standing_addresses.creditable_provider_statuses',
        ['settled'],
    );
    $request = app(CreateFundingRequest::class)->handle(
        new CreateFundingRequestData(
            accountReference: 'wallet:'.$requester->wallet->uuid,
            requesterType: $requester::class,
            requesterId: (string) $requester->getKey(),
            fundingType: FundingRequestType::BankTransfer,
            requestedValueMinor: 25_000,
            currency: 'PHP',
            description: 'InstaPay transfer to the configured NetBank account.',
            idempotencyKey: 'provider-funding-request-250-1001',
            externalReference: 'SENDER-VISIBLE-REFERENCE',
            transferWindow: FundingTransferWindow::Recent,
        ),
    );

    expect($request->voucher->state)->toBe(VoucherState::LOCKED);

    $pending = app(CheckFundingRequestTransfer::class)->handle($request);

    expect($pending->status)->toBe('awaiting_provider_evidence')
        ->and($pending->credited)->toBeFalse()
        ->and($request->refresh()->status)->toBe(FundingRequestStatus::Submitted)
        ->and(VoucherCollection::query()->count())->toBe(0);

    app(RecordProviderFundingObservation::class)->handle(
        new ProviderFundingObservationData(
            provider: 'netbank',
            providerTransactionId: 'NETBANK-INBOUND-250-1001',
            grossAmountMinor: 25_000,
            feeAmountMinor: 0,
            netAmountMinor: 25_000,
            currency: 'PHP',
            providerStatus: 'settled',
            verificationSource: 'netbank-corporate-transaction-history',
            payloadHash: hash('sha256', 'provider-funding-request-250-1001'),
            providerOperationId: 'NETBANK-OPERATION-250-1001',
            occurredAt: new DateTimeImmutable('2026-07-27T21:10:00+08:00'),
            settledAt: new DateTimeImmutable('2026-07-27T21:10:00+08:00'),
            metadata: [
                'destination_verified' => true,
                'connection_reference' => 'netbank-primary',
                'normalization_version' => 'netbank-corporate-history-v1',
            ],
        ),
    );

    $credited = app(CheckFundingRequestTransfer::class)
        ->handle($request->refresh());
    $replay = app(CheckFundingRequestTransfer::class)
        ->handle($request->refresh());

    expect($credited->status)->toBe('credited')
        ->and($credited->credited)->toBeTrue()
        ->and($replay->status)->toBe('already_credited')
        ->and($replay->credited)->toBeFalse()
        ->and($request->refresh()->status)->toBe(FundingRequestStatus::Completed)
        ->and(VoucherCollection::query()->count())->toBe(1)
        ->and(VoucherCollection::query()->sole()->execution_driver)
        ->toBe('x_change_provider_funding')
        ->and(VoucherCollection::query()->sole()->provider_transaction_id)
        ->toBe('NETBANK-INBOUND-250-1001')
        ->and(FundingRequestTransferMatch::query()->sole()->status)
        ->toBe('credited')
        ->and((int) treasuryClientFundsLedger($requester)->balance)
        ->toBe(25_000);

    $this->travelBack();
});

it('reserves an older exact transfer and credits it only after checker approval', function () {
    $this->travelTo(new DateTimeImmutable('2026-07-27T21:30:00+08:00'));
    enableNetbankTreasuryForTests();
    $requester = actingAsTestUser(0);
    $checker = User::query()->create([
        'name' => 'Provider Transfer Checker',
        'email' => 'provider-transfer-checker@example.test',
        'password' => 'password',
    ]);
    config()->set('x-change.funding.requests.checker_ids', [
        (string) $checker->getKey(),
    ]);
    config()->set(
        'x-change.funding.standing_addresses.creditable_provider_statuses',
        ['settled'],
    );
    config()->set(
        'x-change.funding.requests.bank_transfer.automatic_credit_window_minutes',
        10,
    );
    $request = app(CreateFundingRequest::class)->handle(
        new CreateFundingRequestData(
            accountReference: 'wallet:'.$requester->wallet->uuid,
            requesterType: $requester::class,
            requesterId: (string) $requester->getKey(),
            fundingType: FundingRequestType::BankTransfer,
            requestedValueMinor: 30_000,
            currency: 'PHP',
            description: 'Older InstaPay transfer to the configured NetBank account.',
            idempotencyKey: 'provider-funding-request-300-older',
            externalReference: 'AUDIT-ONLY-REFERENCE',
            transferWindow: FundingTransferWindow::LastHour,
        ),
    );
    app(RecordProviderFundingObservation::class)->handle(
        new ProviderFundingObservationData(
            provider: 'netbank',
            providerTransactionId: 'NETBANK-INBOUND-300-OLDER',
            grossAmountMinor: 30_000,
            feeAmountMinor: 0,
            netAmountMinor: 30_000,
            currency: 'PHP',
            providerStatus: 'settled',
            verificationSource: 'netbank-corporate-transaction-history',
            payloadHash: hash('sha256', 'provider-funding-request-300-older'),
            occurredAt: new DateTimeImmutable('2026-07-27T21:00:00+08:00'),
            settledAt: new DateTimeImmutable('2026-07-27T21:00:00+08:00'),
            metadata: [
                'destination_verified' => true,
                'connection_reference' => 'netbank-primary',
            ],
        ),
    );

    $result = app(CheckFundingRequestTransfer::class)->handle($request);
    $replay = app(CheckFundingRequestTransfer::class)
        ->handle($request->refresh());

    expect($result->status)->toBe('approval_required')
        ->and($result->credited)->toBeFalse()
        ->and($replay->status)->toBe('approval_required')
        ->and($request->refresh()->status)
        ->toBe(FundingRequestStatus::AwaitingApproval)
        ->and($request->voucher->refresh()->state)->toBe(VoucherState::LOCKED)
        ->and(FundingRequestTransferMatch::query()->sole()->status)
        ->toBe('awaiting_approval')
        ->and(VoucherCollection::query()->count())->toBe(0)
        ->and((int) $requester->wallet->fresh()->balance)->toBe(0);

    $approval = app(ApproveFundingRequestAndIssueCode::class)->approve(
        $request->refresh(),
        $checker::class,
        (string) $checker->getKey(),
    );

    expect($approval->newlyApproved)->toBeTrue()
        ->and($request->refresh()->status)
        ->toBe(FundingRequestStatus::PayCodeIssued)
        ->and($request->voucher->refresh()->state)->toBe(VoucherState::ACTIVE);

    $collection = app(PayApprovedFundingRequest::class)->handle(
        $request->voucher->refresh(),
    );
    $replayed = app(PayApprovedFundingRequest::class)->handle(
        $request->voucher->refresh(),
    );

    expect($collection->execution_driver)->toBe('x_change_provider_funding')
        ->and($collection->provider_transaction_id)
        ->toBe('NETBANK-INBOUND-300-OLDER')
        ->and($replayed->is($collection))->toBeTrue()
        ->and($request->refresh()->status)->toBe(FundingRequestStatus::Completed)
        ->and(FundingRequestTransferMatch::query()->sole()->status)
        ->toBe('credited')
        ->and(VoucherCollection::query()->count())->toBe(1)
        ->and((int) treasuryClientFundsLedger($requester)->balance)
        ->toBe(30_000);

    $this->travelBack();
});

it('uses the configured automatic credit freshness limit', function () {
    $this->travelTo(new DateTimeImmutable('2026-07-27T21:30:00+08:00'));
    enableNetbankTreasuryForTests();
    $requester = actingAsTestUser(0);
    config()->set(
        'x-change.funding.standing_addresses.creditable_provider_statuses',
        ['settled'],
    );
    config()->set(
        'x-change.funding.requests.bank_transfer.automatic_credit_window_minutes',
        15,
    );
    $request = app(CreateFundingRequest::class)->handle(
        new CreateFundingRequestData(
            accountReference: 'wallet:'.$requester->wallet->uuid,
            requesterType: $requester::class,
            requesterId: (string) $requester->getKey(),
            fundingType: FundingRequestType::BankTransfer,
            requestedValueMinor: 12_300,
            currency: 'PHP',
            description: 'Configured freshness window transfer.',
            idempotencyKey: 'provider-funding-request-configured-window',
            transferWindow: FundingTransferWindow::Recent,
        ),
    );
    app(RecordProviderFundingObservation::class)->handle(
        new ProviderFundingObservationData(
            provider: 'netbank',
            providerTransactionId: 'NETBANK-INBOUND-CONFIGURED-WINDOW',
            grossAmountMinor: 12_300,
            feeAmountMinor: 0,
            netAmountMinor: 12_300,
            currency: 'PHP',
            providerStatus: 'settled',
            verificationSource: 'netbank-corporate-transaction-history',
            payloadHash: hash('sha256', 'provider-funding-request-configured-window'),
            occurredAt: new DateTimeImmutable('2026-07-27T21:18:00+08:00'),
            settledAt: new DateTimeImmutable('2026-07-27T21:18:00+08:00'),
            metadata: [
                'destination_verified' => true,
                'connection_reference' => 'netbank-primary',
            ],
        ),
    );

    $result = app(CheckFundingRequestTransfer::class)->handle($request);

    expect($result->status)->toBe('credited')
        ->and((int) treasuryClientFundsLedger($requester)->balance)
        ->toBe(12_300);

    $this->travelBack();
});
