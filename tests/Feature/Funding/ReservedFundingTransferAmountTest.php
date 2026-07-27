<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Actions\Funding\RecordProviderFundingObservation;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\XChange\Actions\Funding\CheckFundingRequestTransfer;
use LBHurtado\XChange\Actions\Funding\CreateFundingRequest;
use LBHurtado\XChange\Data\Funding\CreateFundingRequestData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Enums\FundingTransferAmountReservationStatus;
use LBHurtado\XChange\Enums\FundingTransferWindow;
use LBHurtado\XChange\Models\FundingTransferAmountReservation;
use LBHurtado\XChange\Models\VoucherCollection;

beforeEach(function (): void {
    config()->set(
        'x-change.funding.requests.bank_transfer.reserved_amounts.enabled',
        true,
    );
    config()->set(
        'x-change.funding.requests.bank_transfer.provider_history_enabled',
        false,
    );
    config()->set(
        'x-change.funding.standing_addresses.creditable_provider_statuses',
        ['settled'],
    );
});

it('reserves a unique exact amount and credits the full amount as Account funds', function () {
    $this->travelTo(new DateTimeImmutable('2026-07-27T12:00:00+08:00'));
    enableNetbankTreasuryForTests();
    $requester = actingAsTestUser(0);
    config()->set(
        'x-change.funding.requests.bank_transfer.reserved_amounts.minimum_adjustment_minor',
        537,
    );
    config()->set(
        'x-change.funding.requests.bank_transfer.reserved_amounts.maximum_adjustment_minor',
        537,
    );
    $request = app(CreateFundingRequest::class)->handle(
        new CreateFundingRequestData(
            accountReference: 'wallet:'.$requester->wallet->uuid,
            requesterType: $requester::class,
            requesterId: (string) $requester->getKey(),
            fundingType: FundingRequestType::BankTransfer,
            requestedValueMinor: 100_000,
            currency: 'PHP',
            description: 'Reserved exact NetBank transfer amount.',
            idempotencyKey: 'reserved-exact-transfer-1000',
            transferWindow: FundingTransferWindow::Recent,
        ),
    );
    $reservation = $request->transferAmountReservation;

    expect($reservation)->toBeInstanceOf(FundingTransferAmountReservation::class)
        ->and($reservation->requested_amount_minor)->toBe(100_000)
        ->and($reservation->matching_adjustment_minor)->toBe(537)
        ->and($reservation->expected_amount_minor)->toBe(100_537)
        ->and($reservation->status)
        ->toBe(FundingTransferAmountReservationStatus::Reserved)
        ->and($request->voucher->instructions->target_amount)->toBe(1_005.37);

    app(RecordProviderFundingObservation::class)->handle(
        new ProviderFundingObservationData(
            provider: 'netbank',
            providerTransactionId: 'NETBANK-RESERVED-EXACT-100537',
            grossAmountMinor: 100_537,
            feeAmountMinor: 0,
            netAmountMinor: 100_537,
            currency: 'PHP',
            providerStatus: 'settled',
            verificationSource: 'netbank-corporate-transaction-history',
            payloadHash: hash('sha256', 'NETBANK-RESERVED-EXACT-100537'),
            occurredAt: new DateTimeImmutable('2026-07-27T12:04:00+08:00'),
            settledAt: new DateTimeImmutable('2026-07-27T12:04:05+08:00'),
            metadata: [
                'destination_verified' => true,
                'connection_reference' => 'netbank-primary',
            ],
        ),
    );

    $result = app(CheckFundingRequestTransfer::class)->handle($request);
    $replay = app(CheckFundingRequestTransfer::class)->handle($request->refresh());

    expect($result->status)->toBe('credited')
        ->and($replay->status)->toBe('already_credited')
        ->and($request->refresh()->requested_value_minor)->toBe(100_000)
        ->and($request->approved_value_minor)->toBe(100_537)
        ->and($request->status)->toBe(FundingRequestStatus::Completed)
        ->and($reservation->refresh()->status)
        ->toBe(FundingTransferAmountReservationStatus::Credited)
        ->and(VoucherCollection::query()->sole()->collected_amount_minor)
        ->toBe(100_537)
        ->and((int) treasuryClientFundsLedger($requester)->balance)
        ->toBe(100_537);

    $this->travelBack();
});

it('does not assign the same active exact amount twice in one provider scope', function () {
    enableNetbankTreasuryForTests();
    $requester = actingAsTestUser(0);
    config()->set(
        'x-change.funding.requests.bank_transfer.reserved_amounts.minimum_adjustment_minor',
        317,
    );
    config()->set(
        'x-change.funding.requests.bank_transfer.reserved_amounts.maximum_adjustment_minor',
        318,
    );

    $create = function (string $idempotencyKey) use ($requester) {
        return app(CreateFundingRequest::class)->handle(
            new CreateFundingRequestData(
                accountReference: 'wallet:'.$requester->wallet->uuid,
                requesterType: $requester::class,
                requesterId: (string) $requester->getKey(),
                fundingType: FundingRequestType::BankTransfer,
                requestedValueMinor: 100_000,
                currency: 'PHP',
                description: 'Collision-safe exact transfer amount.',
                idempotencyKey: $idempotencyKey,
                transferWindow: FundingTransferWindow::Recent,
            ),
        );
    };

    $first = $create('reserved-exact-collision-1');
    $second = $create('reserved-exact-collision-2');

    expect($first->transferAmountReservation->expected_amount_minor)
        ->not->toBe($second->transferAmountReservation->expected_amount_minor)
        ->and(FundingTransferAmountReservation::query()->count())->toBe(2)
        ->and(FundingTransferAmountReservation::query()
            ->distinct()
            ->count('active_key'))->toBe(2);
});

it('does not match the requested base amount without its reserved adjustment', function () {
    $this->travelTo(new DateTimeImmutable('2026-07-27T12:00:00+08:00'));
    enableNetbankTreasuryForTests();
    $requester = actingAsTestUser(0);
    config()->set(
        'x-change.funding.requests.bank_transfer.reserved_amounts.minimum_adjustment_minor',
        537,
    );
    config()->set(
        'x-change.funding.requests.bank_transfer.reserved_amounts.maximum_adjustment_minor',
        537,
    );
    $request = app(CreateFundingRequest::class)->handle(
        new CreateFundingRequestData(
            accountReference: 'wallet:'.$requester->wallet->uuid,
            requesterType: $requester::class,
            requesterId: (string) $requester->getKey(),
            fundingType: FundingRequestType::BankTransfer,
            requestedValueMinor: 100_000,
            currency: 'PHP',
            description: 'Reject the unadjusted transfer amount.',
            idempotencyKey: 'reserved-exact-unadjusted-amount',
            transferWindow: FundingTransferWindow::Recent,
        ),
    );
    app(RecordProviderFundingObservation::class)->handle(
        new ProviderFundingObservationData(
            provider: 'netbank',
            providerTransactionId: 'NETBANK-UNADJUSTED-100000',
            grossAmountMinor: 100_000,
            feeAmountMinor: 0,
            netAmountMinor: 100_000,
            currency: 'PHP',
            providerStatus: 'settled',
            verificationSource: 'netbank-corporate-transaction-history',
            payloadHash: hash('sha256', 'NETBANK-UNADJUSTED-100000'),
            occurredAt: new DateTimeImmutable('2026-07-27T12:04:00+08:00'),
            settledAt: new DateTimeImmutable('2026-07-27T12:04:05+08:00'),
            metadata: [
                'destination_verified' => true,
                'connection_reference' => 'netbank-primary',
            ],
        ),
    );

    $result = app(CheckFundingRequestTransfer::class)->handle($request);

    expect($result->status)->toBe('awaiting_provider_evidence')
        ->and($request->refresh()->status)->toBe(FundingRequestStatus::Submitted)
        ->and(VoucherCollection::query()->count())->toBe(0)
        ->and((int) $requester->wallet->fresh()->balance)->toBe(0);

    $this->travelBack();
});
