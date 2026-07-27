<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\XChange\Actions\Funding\CreateFundingRequest;
use LBHurtado\XChange\Data\Funding\CreateFundingRequestData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Models\FundingRequest;

it('records a user assertion without changing Client Funds', function () {
    $requester = actingAsTestUser(5_000);
    $balanceBefore = (int) $requester->wallet->balance;

    $request = app(CreateFundingRequest::class)->handle(
        new CreateFundingRequestData(
            accountReference: 'wallet:'.$requester->wallet->uuid,
            requesterType: $requester::class,
            requesterId: (string) $requester->getKey(),
            fundingType: FundingRequestType::BankTransfer,
            requestedValueMinor: 2_000_000,
            currency: 'php',
            description: 'Corporate bank transfer for Account Funding.',
            idempotencyKey: 'funding-request-create-1001',
            externalReference: 'BANK-REFERENCE-1001',
            occurredOn: new DateTimeImmutable('2026-07-25'),
            requesterNotes: 'Please match this against the corporate account history.',
        ),
    );

    expect($request->status)->toBe(FundingRequestStatus::Submitted)
        ->and($request->requested_value_minor)->toBe(2_000_000)
        ->and($request->approved_value_minor)->toBeNull()
        ->and($request->voucher)->not->toBeNull()
        ->and($request->voucher->owner->is($requester))->toBeTrue()
        ->and($request->voucher->voucher_type)->toBe(VoucherType::PAYABLE)
        ->and($request->voucher->state)->toBe(VoucherState::LOCKED)
        ->and($request->voucher->instructions->cash->amount)->toBe(0.0)
        ->and($request->voucher->instructions->target_amount)->toBe(20_000.0)
        ->and($request->voucher->instructions->execution?->driver)
        ->toBe('x_change_provider_funding')
        ->and($request->voucher->envelope)->not->toBeNull()
        ->and($request->voucher->envelope->driver_id)
        ->toBe('account-funding-review')
        ->and($request->voucher->envelope->payload)
        ->toMatchArray([
            'request_reference' => $request->reference,
            'requested_value_minor' => 2_000_000,
            'currency' => 'PHP',
        ])
        ->and($request->metadata)->toMatchArray([
            'attachments_enabled' => true,
            'monetary_authority' => 'independent_backing_verification_only',
            'provider_verification_enabled' => true,
        ])
        ->and($request->events)->toHaveCount(1)
        ->and($request->events->first()->event_type)->toBe('submitted')
        ->and((int) $requester->wallet->fresh()->balance)->toBe($balanceBefore);
});

it('replays the same Funding Request idempotently and rejects changed instructions', function () {
    $requester = actingAsTestUser();
    $create = app(CreateFundingRequest::class);
    $data = new CreateFundingRequestData(
        accountReference: 'wallet:'.$requester->wallet->uuid,
        requesterType: $requester::class,
        requesterId: (string) $requester->getKey(),
        fundingType: FundingRequestType::CashHandover,
        requestedValueMinor: 100_000,
        currency: 'PHP',
        description: 'Cash custody request.',
        idempotencyKey: 'funding-request-create-1002',
    );

    $first = $create->handle($data);
    $replay = $create->handle($data);

    expect($replay->is($first))->toBeTrue()
        ->and(FundingRequest::query()->count())->toBe(1)
        ->and($replay->voucher_id)->toBe($first->voucher_id);

    expect(fn () => $create->handle(new CreateFundingRequestData(
        accountReference: $data->accountReference,
        requesterType: $data->requesterType,
        requesterId: $data->requesterId,
        fundingType: $data->fundingType,
        requestedValueMinor: 200_000,
        currency: $data->currency,
        description: $data->description,
        idempotencyKey: $data->idempotencyKey,
    )))->toThrow(RuntimeException::class);
});

it('creates its locked Pay Code when the host uses immutable dates', function () {
    $requester = actingAsTestUser();
    Date::useClass(CarbonImmutable::class);

    try {
        $request = app(CreateFundingRequest::class)->handle(
            new CreateFundingRequestData(
                accountReference: 'wallet:'.$requester->wallet->uuid,
                requesterType: $requester::class,
                requesterId: (string) $requester->getKey(),
                fundingType: FundingRequestType::Unspecified,
                requestedValueMinor: 1_700,
                currency: 'PHP',
                description: 'Browser lifecycle acceptance.',
                idempotencyKey: 'funding-request-immutable-date-1003',
            ),
        );
    } finally {
        Date::useDefault();
    }

    expect($request->voucher)->not->toBeNull()
        ->and($request->voucher->state)->toBe(VoucherState::LOCKED)
        ->and($request->voucher->expires_at)->not->toBeNull();
});
