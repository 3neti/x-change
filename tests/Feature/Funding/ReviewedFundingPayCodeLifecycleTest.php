<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Actions\Funding\ApproveFundingRequestAndIssueCode;
use LBHurtado\XChange\Actions\Funding\ClaimReviewedFundingPayCode;
use LBHurtado\XChange\Actions\Funding\CreateFundingRequest;
use LBHurtado\XChange\Actions\Funding\PrepareFundingRequest;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Data\Funding\CreateFundingRequestData;
use LBHurtado\XChange\Data\Funding\PrepareFundingRequestData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Models\FundingRequestNotice;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Tests\Fakes\User;

it('requires independent approval then moves reserved system value to the bound Account once', function () {
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
    $systemAccountReference = 'wallet:'.$system->wallet->uuid;
    $requesterAccountReference = 'wallet:'.$requester->wallet->uuid;

    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: $systemAccountReference,
        provider: 'netbank',
        amountMinor: 1_000_000,
        currency: 'PHP',
        evidenceReference: 'netbank:system-client-funds:1001',
    );
    $request = app(CreateFundingRequest::class)->handle(new CreateFundingRequestData(
        accountReference: $requesterAccountReference,
        requesterType: $requester::class,
        requesterId: (string) $requester->getKey(),
        fundingType: FundingRequestType::CashHandover,
        requestedValueMinor: 250_000,
        currency: 'PHP',
        description: 'Cash accepted into controlled custody.',
        idempotencyKey: 'reviewed-funding-pay-code-lifecycle-1001',
    ));
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

    expect(fn () => app(ApproveFundingRequestAndIssueCode::class)->handle(
        $prepared,
        $maker::class,
        (string) $maker->getKey(),
    ))->toThrow(RuntimeException::class, 'backing reviewer cannot approve');

    $voucher = app(ApproveFundingRequestAndIssueCode::class)->handle(
        $prepared,
        $checker::class,
        (string) $checker->getKey(),
    );
    $replay = app(ApproveFundingRequestAndIssueCode::class)->handle(
        $prepared,
        $checker::class,
        (string) $checker->getKey(),
    );

    expect($voucher)->toBeInstanceOf(Voucher::class)
        ->and($voucher->instructions->claim?->outcomes[0]->key)
        ->toBe('account_funding')
        ->and($voucher->instructions->claim?->selection)->toBe('server')
        ->and($voucher->instructions->claim?->claimant?->mode)
        ->toBe('recipient')
        ->and(data_get($voucher->metadata, 'treasury.account_funding.status'))
        ->toBe('ready')
        ->and($replay->is($voucher))->toBeTrue()
        ->and(Voucher::query()->count())->toBe(1)
        ->and($request->refresh()->voucher_id)->toBe($voucher->getKey())
        ->and($request->status)->toBe(FundingRequestStatus::PayCodeIssued)
        ->and(FundingRequestNotice::query()->count())->toBe(1)
        ->and(positionBalance($system, TreasuryPositionPurpose::ClientFunds))->toBe(750_000)
        ->and(positionBalance($system, TreasuryPositionPurpose::PayCodeReserve))->toBe(250_000)
        ->and(positionBalance($requester, TreasuryPositionPurpose::ClientFunds))->toBe(0);

    $claimed = app(ClaimReviewedFundingPayCode::class)->handle(
        $request,
        $requester,
    );
    $claimReplay = app(ClaimReviewedFundingPayCode::class)->handle(
        $request,
        $requester,
    );

    expect($claimed)->toBeInstanceOf(VoucherClaim::class)
        ->and($claimed->status)->toBe('succeeded')
        ->and($claimReplay->is($claimed))->toBeTrue()
        ->and($request->refresh()->status)->toBe(FundingRequestStatus::Completed)
        ->and($voucher->refresh()->redeemed_at)->not->toBeNull()
        ->and(positionBalance($system, TreasuryPositionPurpose::ClientFunds))->toBe(750_000)
        ->and(positionBalance($system, TreasuryPositionPurpose::PayCodeReserve))->toBe(0)
        ->and(positionBalance($requester, TreasuryPositionPurpose::ClientFunds))->toBe(250_000);

    fakePayoutProvider()->assertNoDisbursementAttempted();
});

it('rejects a claim from another Account owner', function () {
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    $requester = actingAsTestUser(0);
    $other = User::query()->create([
        'name' => 'Other Account Owner',
        'email' => 'other-account-owner@example.test',
        'password' => 'password',
    ]);
    fundTestUserWallet($other, 0);
    $maker = User::query()->create([
        'name' => 'Maker',
        'email' => 'maker-2@example.test',
        'password' => 'password',
    ]);
    $checker = User::query()->create([
        'name' => 'Checker',
        'email' => 'checker-2@example.test',
        'password' => 'password',
    ]);
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$system->wallet->uuid,
        provider: 'netbank',
        amountMinor: 50_000,
        currency: 'PHP',
        evidenceReference: 'netbank:system-client-funds:1002',
    );
    $request = app(CreateFundingRequest::class)->handle(new CreateFundingRequestData(
        accountReference: 'wallet:'.$requester->wallet->uuid,
        requesterType: $requester::class,
        requesterId: (string) $requester->getKey(),
        fundingType: FundingRequestType::BankTransfer,
        requestedValueMinor: 50_000,
        currency: 'PHP',
        description: 'Matched bank transfer.',
        idempotencyKey: 'reviewed-funding-pay-code-lifecycle-1002',
    ));
    app(PrepareFundingRequest::class)->handle($request, new PrepareFundingRequestData(
        recognizedValueMinor: 50_000,
        currency: 'PHP',
        connectionReference: 'netbank-primary',
        evidenceReference: 'netbank:transaction:1002',
        reviewerType: $maker::class,
        reviewerId: (string) $maker->getKey(),
    ));
    app(ApproveFundingRequestAndIssueCode::class)->handle(
        $request,
        $checker::class,
        (string) $checker->getKey(),
    );

    expect(fn () => app(ClaimReviewedFundingPayCode::class)->handle(
        $request,
        $other,
    ))->toThrow(RuntimeException::class, 'belongs to another Account');
});

function positionBalance(User $owner, TreasuryPositionPurpose $purpose): int
{
    $principal = app(TreasuryPrincipalReferenceResolverContract::class)->resolve($owner);

    return collect(app(TreasuryPositionReadModelContract::class)->forPrincipal($principal))
        ->first(fn ($position): bool => $position->purpose === $purpose)
        ?->balanceMinor ?? 0;
}
