<?php

declare(strict_types=1);

use Bavix\Wallet\Exceptions\InsufficientFunds;
use Illuminate\Http\Request;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\XChange\Actions\Claim\DispatchVoucherClaimOutcome;
use LBHurtado\XChange\Actions\Funding\IssueSystemAccountFundingPayCode;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Actions\Redemption\SubmitWebPayCodeClaim;
use LBHurtado\XChange\Contracts\CockpitHeaderReadModelProviderContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Funding\IssueSystemAccountFundingPayCodeData;
use LBHurtado\XChange\Exceptions\VoucherClaimOutcomeConflict;
use LBHurtado\XChange\Models\ProviderBalanceSnapshot;
use LBHurtado\XChange\Models\SystemAccountFundingPayCodeIssuance;
use LBHurtado\XChange\Services\CheckNetbankSourceAccountReadiness;
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
    $claimReplay = app(DispatchVoucherClaimOutcome::class)->handle(
        voucher: $voucher,
        requestedOutcome: 'account_funding',
        payload: [],
        claimant: $sofia,
    );

    expect($result->claimed)->toBeTrue()
        ->and($sofia->name)->toBe('Sofia Hurtado')
        ->and($sofia->email)->toBe('sofia@hurtado.ph')
        ->and($voucher->claims()
            ->whereKey($claimReplay->getKey())
            ->exists())->toBeTrue()
        ->and($voucher->claims()->count())->toBe(2)
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
        ->and($voucher->claims()->count())->toBe(2)
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

it('lets guest web claims use the onboarding driver before Account Funding settlement', function (): void {
    config()->set('x-change.onboarding.voucher.require_otp', false);
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    config()->set('x-change.provider_runtime.payout_provider_hint', null);

    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    fundTestSystemAccountFundingReserve(
        $system,
        10_000,
        'web-onboarding-grant',
    );
    $request = Request::create('/x/claim/MAKE-TEST', 'POST');
    $session = app('session')->driver();
    $session->start();
    $request->setLaravelSession($session);
    app()->instance(Request::class, $request);
    auth()->logout();
    $readiness = Mockery::mock(CheckNetbankSourceAccountReadiness::class);
    $readiness->shouldReceive('handle')
        ->once()
        ->with()
        ->andReturn([
            'enabled' => true,
            'ready' => true,
            'checked' => true,
            'account_number_masked' => '********0019',
            'balance_minor' => 507_693,
            'available_balance_minor' => 507_693,
            'currency' => 'PHP',
            'as_of' => now()->subSecond()->toIso8601String(),
            'fetched_at' => now()->toIso8601String(),
            'message' => 'NetBank source account balance was refreshed.',
        ]);
    app()->instance(CheckNetbankSourceAccountReadiness::class, $readiness);

    $issuance = app(IssueSystemAccountFundingPayCode::class)->handle(
        new IssueSystemAccountFundingPayCodeData(
            amountMinor: 10_000,
            connectionReference: 'netbank-primary',
            idempotencyReference: 'web-onboarding-grant-20260908-001',
            expiresAt: now()->addDay(),
            evidenceReference: 'system-reserve:web-onboarding-grant',
            authorizationReference: 'system-policy:onboarding-grant-v1',
            source: 'commissioning_invitation',
            onboarding: true,
            prefix: 'MAKE',
            riderMessage: 'x-PayOut Maker onboarding invitation',
            onboardingProfile: 'x-payout-maker',
        ),
    );
    $voucher = $issuance->voucher;

    expect($voucher)->not->toBeNull()
        ->and(data_get($voucher?->metadata, 'instructions.execution.driver'))
        ->toBe('onboarding_account_provisioning')
        ->and(data_get($voucher?->metadata, 'instructions.claim.default_outcome'))
        ->toBe('account_funding');

    $result = app(SubmitWebPayCodeClaim::class)->handle($voucher, [
        'mobile' => '639173011987',
        'recipient_country' => 'PH',
        'inputs' => [
            'full_name' => 'Lester Hurtado',
            'name' => 'Lester Hurtado',
            'email' => 'lester.onboarding@example.test',
            'mobile' => '639173011987',
        ],
    ]);
    $claimant = User::query()
        ->where('email', 'lester.onboarding@example.test')
        ->sole();
    $snapshot = ProviderBalanceSnapshot::query()
        ->where('provider_code', 'netbank')
        ->where('balance_key', 'netbank_source_account')
        ->sole();
    $header = app(CockpitHeaderReadModelProviderContract::class)
        ->forOperator($claimant)
        ->toArray();

    expect($result->claimed)->toBeTrue()
        ->and($result->status)->toBe('redeemed')
        ->and($claimant->name)->toBe('Lester Hurtado')
        ->and(auth()->user()?->is($claimant))->toBeTrue()
        ->and(systemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe(0)
        ->and(systemFundingPositionBalance(
            $claimant,
            TreasuryPositionPurpose::ClientFunds,
        ))->toBe(10_000)
        ->and($snapshot->available_balance_minor)->toBe(507_693)
        ->and($snapshot->refresh_status)->toBe('fresh')
        ->and($header['balances'][0]['value'])->toContain('100.00')
        ->and($header['balances'][1]['value'])->toContain('0.00')
        ->and($header['balances'][2]['value'])->toContain('100.00')
        ->and($header['balances'][2]['value'])->not->toBe('Not available')
        ->and($voucher->refresh()->redeemed_at)->not->toBeNull()
        ->and($voucher->claims()->count())->toBe(2)
        ->and(ExecutionJournalEntry::query()
            ->orderBy('id')
            ->pluck('event_type')
            ->all())->toBe([
                'account_funding.pay_code.issued',
                'account_funding.pay_code.outcome_selected',
                'account_funding.pay_code.applied',
            ]);

    $claimReplay = app(DispatchVoucherClaimOutcome::class)->handle(
        voucher: $voucher,
        requestedOutcome: 'account_funding',
        payload: [],
        claimant: $claimant,
    );

    expect($claimReplay->status)->toBe('succeeded')
        ->and($voucher->claims()->count())->toBe(2)
        ->and(systemFundingPositionBalance(
            $claimant,
            TreasuryPositionPurpose::ClientFunds,
        ))->toBe(10_000)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'account_funding.pay_code.applied')
            ->count())->toBe(1);
});

it('still rejects guest web claims for direct Account Funding Pay Codes', function (): void {
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    fundTestSystemAccountFundingReserve(
        $system,
        5_000,
        'direct-account-funding-guest',
    );
    auth()->logout();

    $issuance = app(IssueSystemAccountFundingPayCode::class)->handle(
        new IssueSystemAccountFundingPayCodeData(
            amountMinor: 5_000,
            connectionReference: 'netbank-primary',
            idempotencyReference: 'direct-account-funding-guest-20260908-001',
            expiresAt: now()->addDay(),
            evidenceReference: 'system-reserve:direct-account-funding-guest',
            authorizationReference: 'system-policy:direct-account-funding-v1',
            source: 'system_utility',
        ),
    );
    $voucher = $issuance->voucher;

    expect(fn () => app(SubmitWebPayCodeClaim::class)->handle($voucher, [
        'mobile' => '639173011987',
    ]))->toThrow(
        VoucherClaimOutcomeConflict::class,
        'Account Funding requires an authenticated Account owner.',
    );

    expect(systemFundingPositionBalance(
        $system,
        TreasuryPositionPurpose::PayCodeReserve,
    ))->toBe(5_000)
        ->and($voucher->refresh()->redeemed_at)->toBeNull()
        ->and($voucher->claims()->count())->toBe(0);
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
