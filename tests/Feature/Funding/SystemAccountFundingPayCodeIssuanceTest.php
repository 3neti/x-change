<?php

declare(strict_types=1);

use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Actions\Claim\DispatchVoucherClaimOutcome;
use LBHurtado\XChange\Actions\Funding\IssueSystemAccountFundingPayCode;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Data\Funding\IssueSystemAccountFundingPayCodeData;
use LBHurtado\XChange\Models\SystemAccountFundingPayCodeIssuance;

it('issues and replays one recipient-bound Account Funding Pay Code from system Client Funds', function (): void {
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    $recipient = actingAsTestUser(0);

    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$system->wallet->uuid,
        provider: 'netbank',
        amountMinor: 500_000,
        currency: 'PHP',
        evidenceReference: 'netbank:system-client-funds:utility-1001',
    );

    $request = new IssueSystemAccountFundingPayCodeData(
        amountMinor: 125_000,
        connectionReference: 'netbank-primary',
        idempotencyReference: 'system-account-funding-utility-1001',
        expiresAt: now()->addDay(),
        recipient: $recipient,
        evidenceReference: 'test-evidence:utility-1001',
    );
    $issuance = app(IssueSystemAccountFundingPayCode::class)->handle($request);
    $replay = app(IssueSystemAccountFundingPayCode::class)->handle($request);
    $voucher = $issuance->voucher;

    expect($issuance->status)->toBe('issued')
        ->and($issuance->bearer)->toBeFalse()
        ->and($issuance->amount_minor)->toBe(125_000)
        ->and($issuance->connection_reference)->toBe('netbank-primary')
        ->and($issuance->reservation_operation_reference)->not->toBeNull()
        ->and($replay->is($issuance))->toBeTrue()
        ->and($replay->voucher?->is($voucher))->toBeTrue()
        ->and(SystemAccountFundingPayCodeIssuance::query()->count())->toBe(1)
        ->and($voucher?->instructions->claim?->outcomes[0]->key)
        ->toBe('account_funding')
        ->and($voucher?->instructions->claim?->claimant?->mode)
        ->toBe('recipient')
        ->and(data_get($voucher?->metadata, 'treasury.account_funding.status'))
        ->toBe('ready')
        ->and(systemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::ClientFunds,
        ))->toBe(375_000)
        ->and(systemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe(125_000);

    $claim = app(DispatchVoucherClaimOutcome::class)->handle(
        voucher: $voucher,
        requestedOutcome: 'account_funding',
        payload: [],
        claimant: $recipient,
    );

    expect($claim->status)->toBe('succeeded')
        ->and(systemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe(0)
        ->and(systemFundingPositionBalance(
            $recipient,
            TreasuryPositionPurpose::ClientFunds,
        ))->toBe(125_000);

    fakePayoutProvider()->assertNoDisbursementAttempted();
});

it('rejects reuse of an issuance reference with different economic inputs', function (): void {
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    $recipient = actingAsTestUser(0);

    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$system->wallet->uuid,
        provider: 'netbank',
        amountMinor: 500_000,
        currency: 'PHP',
        evidenceReference: 'netbank:system-client-funds:utility-1002',
    );
    $expiresAt = now()->addDay();
    $action = app(IssueSystemAccountFundingPayCode::class);

    $action->handle(new IssueSystemAccountFundingPayCodeData(
        amountMinor: 100_000,
        connectionReference: 'netbank-primary',
        idempotencyReference: 'system-account-funding-utility-1002',
        expiresAt: $expiresAt,
        recipient: $recipient,
    ));

    expect(fn () => $action->handle(
        new IssueSystemAccountFundingPayCodeData(
            amountMinor: 100_001,
            connectionReference: 'netbank-primary',
            idempotencyReference: 'system-account-funding-utility-1002',
            expiresAt: $expiresAt,
            recipient: $recipient,
        ),
    ))->toThrow(
        RuntimeException::class,
        'already used with different inputs',
    );

    expect(SystemAccountFundingPayCodeIssuance::query()->count())->toBe(1);
});

function systemFundingPositionBalance(
    object $owner,
    TreasuryPositionPurpose $purpose,
): int {
    $principal = app(
        TreasuryPrincipalReferenceResolverContract::class,
    )->resolve($owner);

    return collect(
        app(TreasuryPositionReadModelContract::class)->forPrincipal($principal),
    )->first(
        static fn ($position): bool => $position->purpose === $purpose,
    )?->balanceMinor ?? 0;
}
