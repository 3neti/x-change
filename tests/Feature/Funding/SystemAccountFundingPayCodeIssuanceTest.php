<?php

declare(strict_types=1);

use Bavix\Wallet\Exceptions\InsufficientFunds;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\XChange\Actions\Claim\DispatchVoucherClaimOutcome;
use LBHurtado\XChange\Actions\Funding\IssueSystemAccountFundingPayCode;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Funding\IssueSystemAccountFundingPayCodeData;
use LBHurtado\XChange\Models\SystemAccountFundingPayCodeIssuance;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

it('issues and replays one recipient-bound Account Funding Pay Code from the system Account Funding Reserve', function (): void {
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    $recipient = actingAsTestUser(0);

    fundTestSystemAccountFundingReserve(
        $system,
        500_000,
        'utility-1001',
    );

    $request = new IssueSystemAccountFundingPayCodeData(
        amountMinor: 125_000,
        connectionReference: 'netbank-primary',
        idempotencyReference: 'system-account-funding-utility-1001',
        expiresAt: now()->addDay(),
        recipient: $recipient,
        evidenceReference: 'test-evidence:utility-1001',
        authorizationReference: 'test-authorization:utility-1001',
    );
    $issuance = app(IssueSystemAccountFundingPayCode::class)->handle($request);
    $replay = app(IssueSystemAccountFundingPayCode::class)->handle($request);
    $voucher = $issuance->voucher;

    expect($issuance->status)->toBe('issued')
        ->and($issuance->bearer)->toBeFalse()
        ->and($issuance->amount_minor)->toBe(125_000)
        ->and($issuance->connection_reference)->toBe('netbank-primary')
        ->and($issuance->reservation_operation_reference)->not->toBeNull()
        ->and($issuance->authorization_reference)
        ->toBe('test-authorization:utility-1001')
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
            TreasuryPositionPurpose::AccountFundingReserve,
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
        ))->toBe(125_000)
        ->and(ExecutionJournalEntry::query()
            ->orderBy('id')
            ->pluck('event_type')
            ->all())->toBe([
                'account_funding.pay_code.issued',
                'account_funding.pay_code.outcome_selected',
                'account_funding.pay_code.applied',
            ])
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'account_funding.pay_code.applied')
            ->sole()
            ->references['metadata']['treasury_operation_reference'])
        ->toBe($claim->treasury_operation_reference);

    fakePayoutProvider()->assertNoDisbursementAttempted();
});

it('rejects reuse of an issuance reference with different economic inputs', function (): void {
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    $recipient = actingAsTestUser(0);

    fundTestSystemAccountFundingReserve(
        $system,
        500_000,
        'utility-1002',
    );
    $expiresAt = now()->addDay();
    $action = app(IssueSystemAccountFundingPayCode::class);

    $action->handle(new IssueSystemAccountFundingPayCodeData(
        amountMinor: 100_000,
        connectionReference: 'netbank-primary',
        idempotencyReference: 'system-account-funding-utility-1002',
        expiresAt: $expiresAt,
        recipient: $recipient,
        evidenceReference: 'test-evidence:utility-1002',
        authorizationReference: 'test-authorization:utility-1002',
    ));

    expect(fn () => $action->handle(
        new IssueSystemAccountFundingPayCodeData(
            amountMinor: 100_001,
            connectionReference: 'netbank-primary',
            idempotencyReference: 'system-account-funding-utility-1002',
            expiresAt: $expiresAt,
            recipient: $recipient,
            evidenceReference: 'test-evidence:utility-1002',
            authorizationReference: 'test-authorization:utility-1002',
        ),
    ))->toThrow(
        RuntimeException::class,
        'already used with different inputs',
    );

    expect(fn () => $action->handle(
        new IssueSystemAccountFundingPayCodeData(
            amountMinor: 100_000,
            connectionReference: 'netbank-primary',
            idempotencyReference: 'system-account-funding-utility-1002',
            expiresAt: $expiresAt,
            recipient: $recipient,
            evidenceReference: 'test-evidence:utility-1002',
            authorizationReference: 'different-authorization:utility-1002',
        ),
    ))->toThrow(
        RuntimeException::class,
        'already used with different inputs',
    );

    expect(SystemAccountFundingPayCodeIssuance::query()->count())->toBe(1);
});

it('rejects direct issuance without evidence and authorization references', function (): void {
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    $recipient = actingAsTestUser(0);
    fundTestSystemAccountFundingReserve(
        $system,
        50_000,
        'utility-missing-controls',
    );

    expect(fn () => app(IssueSystemAccountFundingPayCode::class)
        ->handle(new IssueSystemAccountFundingPayCodeData(
            amountMinor: 1_000,
            connectionReference: 'netbank-primary',
            idempotencyReference: 'utility-missing-controls',
            expiresAt: now()->addDay(),
            recipient: $recipient,
            evidenceReference: 'test-evidence:missing-controls',
        )))->toThrow(
            RuntimeException::class,
            'authorization reference is invalid',
        );

    expect(SystemAccountFundingPayCodeIssuance::query()->count())
        ->toBe(0);
});

it('atomically provisions a new Account and funds it from the system Account Funding Reserve', function (): void {
    config()->set('x-change.onboarding.voucher.require_otp', false);

    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    fundTestSystemAccountFundingReserve(
        $system,
        1_802,
        'onboarding-grant-sofia',
    );
    $inventoryBefore = TreasuryInventory::query()
        ->sum('balance_minor');

    $request = new IssueSystemAccountFundingPayCodeData(
        amountMinor: 1_500,
        connectionReference: 'netbank-primary',
        idempotencyReference: 'onboarding-grant-sofia-20260730-001',
        expiresAt: now()->addDay(),
        evidenceReference: 'system-reserve:onboarding-grant-sofia',
        authorizationReference: 'system-policy:onboarding-grant-v1',
        source: 'treasury_onboarding_grant',
        onboarding: true,
    );
    $issuance = app(IssueSystemAccountFundingPayCode::class)->handle($request);
    $replay = app(IssueSystemAccountFundingPayCode::class)->handle($request);
    $voucher = $issuance->voucher;

    expect($voucher)->not->toBeNull()
        ->and($replay->is($issuance))->toBeTrue()
        ->and(data_get($voucher?->metadata, 'instructions.onboarding'))->toBeTrue()
        ->and(data_get($voucher?->metadata, 'instructions.execution.driver'))
        ->toBe('onboarding_account_provisioning')
        ->and(data_get($voucher?->metadata, 'instructions.claim.default_outcome'))
        ->toBe('account_funding')
        ->and(systemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::AccountFundingReserve,
        ))->toBe(302)
        ->and(systemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe(1_500);

    $result = app(SubmitPayCodeClaim::class)->handle($voucher, [
        'mobile' => '639399236237',
        'recipient_country' => 'PH',
        'inputs' => [
            'full_name' => 'Sofia Hurtado',
            'name' => 'Sofia Hurtado',
            'email' => 'sofia@hurtado.ph',
            'mobile' => '639399236237',
        ],
    ]);
    $sofia = User::query()
        ->where('mobile', '639399236237')
        ->sole();
    $claim = $voucher->claims()->sole();
    $claimReplay = app(DispatchVoucherClaimOutcome::class)->handle(
        voucher: $voucher,
        requestedOutcome: 'account_funding',
        payload: [],
        claimant: $sofia,
    );

    expect($result->claimed)->toBeTrue()
        ->and($sofia->name)->toBe('Sofia Hurtado')
        ->and($sofia->email)->toBe('sofia@hurtado.ph')
        ->and($claimReplay->is($claim))->toBeTrue()
        ->and(systemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::AccountFundingReserve,
        ))->toBe(302)
        ->and(systemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe(0)
        ->and(systemFundingPositionBalance(
            $sofia,
            TreasuryPositionPurpose::ClientFunds,
        ))->toBe(1_500)
        ->and(TreasuryInventory::query()
            ->sum('balance_minor'))->toBe($inventoryBefore)
        ->and($voucher->claims()->count())->toBe(1)
        ->and(ExecutionJournalEntry::query()
            ->orderBy('id')
            ->pluck('event_type')
            ->all())->toBe([
                'account_funding.pay_code.issued',
                'account_funding.pay_code.outcome_selected',
                'account_funding.pay_code.applied',
            ]);

    fakePayoutProvider()->assertNoDisbursementAttempted();
});

it('rolls back onboarding grant issuance when the system reserve is insufficient', function (): void {
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    fundTestSystemAccountFundingReserve(
        $system,
        1_499,
        'onboarding-grant-insufficient',
    );

    expect(fn () => app(IssueSystemAccountFundingPayCode::class)->handle(
        new IssueSystemAccountFundingPayCodeData(
            amountMinor: 1_500,
            connectionReference: 'netbank-primary',
            idempotencyReference: 'onboarding-grant-insufficient',
            expiresAt: now()->addDay(),
            evidenceReference: 'system-reserve:onboarding-grant-insufficient',
            authorizationReference: 'system-policy:onboarding-grant-v1',
            source: 'treasury_onboarding_grant',
            onboarding: true,
        ),
    ))->toThrow(InsufficientFunds::class);

    expect(SystemAccountFundingPayCodeIssuance::query()->count())->toBe(0)
        ->and(systemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::AccountFundingReserve,
        ))->toBe(1_499)
        ->and(systemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe(0)
        ->and(ExecutionJournalEntry::query()->count())->toBe(0);

    fakePayoutProvider()->assertNoDisbursementAttempted();
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
