<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Events\FundingProjectionChanged;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Services\Treasury\TreasuryPayCodeAccountingService;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

it('returns an unclaimed Pay Code reserve to Client Funds exactly once', function () {
    $issuer = actingAsTestUser();
    enableNetbankTreasuryForTests();
    $provider = fakePayoutProvider();
    Event::fake([FundingProjectionChanged::class]);
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $issuer,
        ['netbank-primary'],
    );
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$issuer->wallet->uuid,
        provider: 'netbank',
        amountMinor: 50_000,
        currency: 'PHP',
        evidenceReference: 'netbank:pay-code-terminal-release',
    );
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 200,
        overrides: [
            'metadata' => [
                'issuer_id' => (string) $issuer->getKey(),
                'commercial_charge_reference' => 'commercial-charge:kept',
            ],
        ],
    ));
    app(TreasuryPayCodeAccountingService::class)->reserve(
        accountOwner: $issuer,
        voucher: $voucher,
        connectionReference: 'netbank-primary',
        providerPrincipalMinor: 20_000,
        currency: 'PHP',
    );
    $clientFundsBefore = terminalReleasePositionBalance(
        $issuer,
        TreasuryPositionPurpose::ClientFunds,
    );
    $reserveBefore = terminalReleasePositionBalance(
        $issuer,
        TreasuryPositionPurpose::PayCodeReserve,
    );
    $inventoryBefore = TreasuryInventory::query()->sum('balance_minor');
    $commercialMetadataBefore = data_get(
        $voucher->refresh()->metadata,
        'instructions.metadata.commercial_charge_reference',
    );

    $first = app(VoucherLifecycleServiceContract::class)->cancel(
        (string) $voucher->getKey(),
        ['reason' => 'customer_requested'],
    );
    $closedAt = $voucher->refresh()->closed_at?->toIso8601String();

    expect($first['treasury_release'])
        ->toMatchArray([
            'status' => 'released',
            'terminal_reason' => 'cancelled',
            'amount_minor' => 20_000,
            'currency' => 'PHP',
            'replayed' => false,
            'provider_calls' => false,
            'provider_inventory_changed' => false,
            'issuance_charges_refunded' => false,
        ])
        ->and(terminalReleasePositionBalance(
            $issuer,
            TreasuryPositionPurpose::ClientFunds,
        ))->toBe($clientFundsBefore + 20_000)
        ->and(terminalReleasePositionBalance(
            $issuer,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe($reserveBefore - 20_000)
        ->and(TreasuryInventory::query()->sum('balance_minor'))
        ->toBe($inventoryBefore)
        ->and($voucher->refresh()->state)->toBe(VoucherState::CLOSED)
        ->and(data_get(
            $voucher->metadata,
            'treasury.pay_code_reservation.status',
        ))->toBe('released')
        ->and(data_get(
            $voucher->metadata,
            'instructions.metadata.commercial_charge_reference',
        ))->toBe($commercialMetadataBefore)
        ->and(TreasuryPositionOperation::query()
            ->where('operation_type', TreasuryPositionOperationType::Release)
            ->count())->toBe(1)
        ->and(TreasuryPositionOperation::query()
            ->where(
                'operation_type',
                TreasuryPositionOperationType::CommercialReversal,
            )
            ->count())->toBe(0)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'pay_code.reserve.released')
            ->count())->toBe(1);
    $provider->assertNoDisbursementAttempted();
    Event::assertDispatched(
        FundingProjectionChanged::class,
        fn (FundingProjectionChanged $event): bool => $event->broadcastWith()['reason']
            === 'pay_code_reserve_released',
    );

    $second = app(VoucherLifecycleServiceContract::class)->cancel(
        (string) $voucher->getKey(),
        ['reason' => 'customer_requested_replay'],
    );

    expect($second['treasury_release']['replayed'])->toBeTrue()
        ->and(terminalReleasePositionBalance(
            $issuer,
            TreasuryPositionPurpose::ClientFunds,
        ))->toBe($clientFundsBefore + 20_000)
        ->and(terminalReleasePositionBalance(
            $issuer,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe($reserveBefore - 20_000)
        ->and(TreasuryInventory::query()->sum('balance_minor'))
        ->toBe($inventoryBefore)
        ->and($voucher->refresh()->closed_at?->toIso8601String())
        ->toBe($closedAt)
        ->and(TreasuryPositionOperation::query()
            ->where('operation_type', TreasuryPositionOperationType::Release)
            ->count())->toBe(1)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'pay_code.reserve.released')
            ->count())->toBe(1);
    Event::assertDispatchedTimes(FundingProjectionChanged::class, 1);
    $provider->assertNoDisbursementAttempted();
});

it('does not cancel or release a claimed Pay Code', function () {
    $issuer = actingAsTestUser();
    enableNetbankTreasuryForTests();
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $issuer,
        ['netbank-primary'],
    );
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$issuer->wallet->uuid,
        provider: 'netbank',
        amountMinor: 20_000,
        currency: 'PHP',
        evidenceReference: 'netbank:claimed-pay-code-terminal-release',
    );
    $voucher = issueVoucher(validVoucherInstructions(amount: 200));
    app(TreasuryPayCodeAccountingService::class)->reserve(
        accountOwner: $issuer,
        voucher: $voucher,
        connectionReference: 'netbank-primary',
        providerPrincipalMinor: 20_000,
        currency: 'PHP',
    );
    $voucher->forceFill(['redeemed_at' => now()])->saveQuietly();
    $clientFundsBefore = terminalReleasePositionBalance(
        $issuer,
        TreasuryPositionPurpose::ClientFunds,
    );
    $reserveBefore = terminalReleasePositionBalance(
        $issuer,
        TreasuryPositionPurpose::PayCodeReserve,
    );

    expect(fn () => app(VoucherLifecycleServiceContract::class)->cancel(
        (string) $voucher->getKey(),
        ['reason' => 'must_not_release'],
    ))->toThrow(RuntimeException::class, 'cannot be cancelled or released')
        ->and(terminalReleasePositionBalance(
            $issuer,
            TreasuryPositionPurpose::ClientFunds,
        ))->toBe($clientFundsBefore)
        ->and(terminalReleasePositionBalance(
            $issuer,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe($reserveBefore)
        ->and($voucher->refresh()->state)->not->toBe(VoucherState::CLOSED);
});

it('allows only the Pay Code owner to cancel a reserved Pay Code', function () {
    $issuer = actingAsTestUser();
    enableNetbankTreasuryForTests();
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $issuer,
        ['netbank-primary'],
    );
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$issuer->wallet->uuid,
        provider: 'netbank',
        amountMinor: 20_000,
        currency: 'PHP',
        evidenceReference: 'netbank:owner-authorized-terminal-release',
    );
    $voucher = issueVoucher(validVoucherInstructions(amount: 200));
    app(TreasuryPayCodeAccountingService::class)->reserve(
        accountOwner: $issuer,
        voucher: $voucher,
        connectionReference: 'netbank-primary',
        providerPrincipalMinor: 20_000,
        currency: 'PHP',
    );
    $clientFundsBefore = terminalReleasePositionBalance(
        $issuer,
        TreasuryPositionPurpose::ClientFunds,
    );
    $reserveBefore = terminalReleasePositionBalance(
        $issuer,
        TreasuryPositionPurpose::PayCodeReserve,
    );
    $otherAccount = User::query()->create([
        'name' => 'Other Account',
        'email' => 'other-account@example.test',
        'password' => 'password',
    ]);
    test()->actingAs($otherAccount);

    expect(fn () => app(VoucherLifecycleServiceContract::class)->cancel(
        (string) $voucher->getKey(),
        ['reason' => 'not_the_owner'],
    ))->toThrow(AuthorizationException::class, 'Only the Pay Code owner')
        ->and(terminalReleasePositionBalance(
            $issuer,
            TreasuryPositionPurpose::ClientFunds,
        ))->toBe($clientFundsBefore)
        ->and(terminalReleasePositionBalance(
            $issuer,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe($reserveBefore)
        ->and($voucher->refresh()->state)->not->toBe(VoucherState::CLOSED);
});

it('refuses to mispost an Account Funding Reserve return into Client Funds', function () {
    $issuer = actingAsTestUser();
    enableNetbankTreasuryForTests();
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $issuer,
        ['netbank-primary'],
    );
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$issuer->wallet->uuid,
        provider: 'netbank',
        amountMinor: 20_000,
        currency: 'PHP',
        evidenceReference: 'netbank:system-funded-terminal-release',
    );
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 200,
        overrides: [
            'metadata' => [
                'issuer_id' => (string) $issuer->getKey(),
            ],
        ],
    ));
    app(TreasuryPayCodeAccountingService::class)->reserve(
        accountOwner: $issuer,
        voucher: $voucher,
        connectionReference: 'netbank-primary',
        providerPrincipalMinor: 20_000,
        currency: 'PHP',
    );
    $metadata = $voucher->refresh()->metadata;
    data_set(
        $metadata,
        'treasury.pay_code_reservation.source_position_purpose',
        TreasuryPositionPurpose::AccountFundingReserve->value,
    );
    $voucher->forceFill(['metadata' => $metadata])->saveQuietly();
    $clientFundsBefore = terminalReleasePositionBalance(
        $issuer,
        TreasuryPositionPurpose::ClientFunds,
    );
    $reserveBefore = terminalReleasePositionBalance(
        $issuer,
        TreasuryPositionPurpose::PayCodeReserve,
    );

    expect(fn () => app(VoucherLifecycleServiceContract::class)->cancel(
        (string) $voucher->getKey(),
        ['reason' => 'wrong_origin'],
    ))->toThrow(
        TreasuryConfigurationException::class,
        'cancellation cannot return it to Client Funds',
    )
        ->and(terminalReleasePositionBalance(
            $issuer,
            TreasuryPositionPurpose::ClientFunds,
        ))->toBe($clientFundsBefore)
        ->and(terminalReleasePositionBalance(
            $issuer,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe($reserveBefore)
        ->and($voucher->refresh()->state)->not->toBe(VoucherState::CLOSED);
});

function terminalReleasePositionBalance(
    User $owner,
    TreasuryPositionPurpose $purpose,
): int {
    $position = TreasuryPosition::query()
        ->whereMorphedTo('principal', $owner)
        ->where('connection_reference', 'netbank-primary')
        ->where('purpose', $purpose)
        ->sole();

    return (int) Wallet::query()
        ->findOrFail($position->internal_ledger_id)
        ->balance;
}
