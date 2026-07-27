<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use LBHurtado\EmiCore\Actions\Funding\RecordProviderFundingObservation;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\XChange\Actions\Funding\CheckFundingRequestTransfer;
use LBHurtado\XChange\Actions\Funding\CreateFundingRequest;
use LBHurtado\XChange\Data\Funding\CreateFundingRequestData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Events\FundingProjectionChanged;
use LBHurtado\XChange\Events\FundingRequestChanged;
use LBHurtado\XChange\Models\VoucherCollection;

it('credits an exact provider-observed bank transfer once through the PAYABLE collection engine', function () {
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
            externalReference: '026208691236',
        ),
    );

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
            providerOperationId: '026208691236',
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
        ->and((int) treasuryClientFundsLedger($requester)->balance)
        ->toBe(25_000);
});
