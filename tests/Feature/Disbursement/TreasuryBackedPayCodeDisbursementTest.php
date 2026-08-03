<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use Illuminate\Support\Facades\Bus;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Actions\Funding\IssueTreasuryBackedPayCode;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Jobs\Redemption\DispatchVoucherRedemptionFeedbackJob;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Treasury\TreasuryPayCodeAccountingService;

it('pays a treasury-backed pay code without a legacy cash entity', function (): void {
    Bus::fake([DispatchVoucherRedemptionFeedbackJob::class]);
    $issuer = actingAsTestUser();
    enableNetbankTreasuryForTests();
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $issuer,
        ['netbank-primary'],
    );
    $inventoryOperations = app(TreasuryInventoryOperationContract::class);
    $inventoryOperations->registerInventory(new TreasuryInventoryData(
        inventoryReference: 'inventory:netbank:vca-cash',
        resourceType: 'cash_at_bank',
        currency: 'PHP',
        capacityMinor: 0,
        status: 'requested',
        idempotencyKey: 'register:inventory:netbank:treasury-backed-payout',
        externalReference: 'resource:netbank:corporate-vca',
    ));
    $inventoryOperations->recognize(new TreasuryInventoryRecognitionData(
        operationReference: 'funding-recognition:netbank:treasury-backed-payout',
        inventoryReference: 'inventory:netbank:vca-cash',
        settlementResourceReference: 'resource:netbank:corporate-vca',
        amountMinor: 5_000,
        currency: 'PHP',
        status: 'requested',
        idempotencyKey: 'funding-recognition-key:netbank:treasury-backed-payout',
        externalReference: 'netbank:treasury-backed-payout',
    ));
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$issuer->wallet->uuid,
        provider: 'netbank',
        amountMinor: 5_000,
        currency: 'PHP',
        evidenceReference: 'netbank:treasury-backed-payout',
    );
    $voucher = app(IssueTreasuryBackedPayCode::class)->handle(
        issuer: $issuer,
        instructions: validVoucherInstructions(
            amount: 20,
            overrides: [
                'metadata' => [
                    'flow_type' => 'disbursable',
                    'issuer_id' => (string) $issuer->getKey(),
                ],
            ],
        )->toArray(),
        expiresAt: now()->addWeek(),
    );
    app(TreasuryPayCodeAccountingService::class)->reserve(
        accountOwner: $issuer,
        voucher: $voucher,
        connectionReference: 'netbank-primary',
        providerPrincipalMinor: 2_000,
        currency: 'PHP',
    );
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(
        transactionId: 'NETBANK-TREASURY-PAYOUT-1',
        provider: 'netbank',
    );
    $reserveBefore = treasuryBackedPayoutPositionBalance(
        $issuer,
        TreasuryPositionPurpose::PayCodeReserve,
    );
    $inventoryBefore = (int) TreasuryInventory::query()->sum('balance_minor');

    $result = app(SubmitPayCodeClaim::class)->handle($voucher, [
        'mobile' => '09173011987',
        'recipient_country' => 'PH',
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
        ],
    ]);

    $reconciliation = DisbursementReconciliation::query()
        ->where('voucher_id', $voucher->getKey())
        ->sole();

    expect($result->claimed)->toBeTrue()
        ->and($result->status)->toBe('succeeded')
        ->and($result->disbursed_amount)->toBe(20.0)
        ->and($voucher->refresh()->cash)->toBeNull()
        ->and($reconciliation->status)->toBe('succeeded')
        ->and($reconciliation->provider_transaction_id)->toBe('NETBANK-TREASURY-PAYOUT-1')
        ->and($reconciliation->internal_status)->toBe('finalized')
        ->and(data_get(
            $voucher->metadata,
            'treasury.pay_code_reservation.status',
        ))->toBe('settled')
        ->and(treasuryBackedPayoutPositionBalance(
            $issuer,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe($reserveBefore - 2_000)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))
        ->toBe($inventoryBefore - 2_000);
    expect(VoucherClaim::query()->where('voucher_id', $voucher->getKey())->sole())
        ->status->toBe('succeeded')
        ->disbursed_amount_minor->toBe(2_000);
    $provider->assertDisburseCalledTimes(1);
    expect($provider->lastRequest?->amount)->toBe(20.0)
        ->and($provider->lastRequest?->bank_code)->toBe('GXCHPHM2XXX')
        ->and($provider->lastRequest?->account_number)->toBe('09173011987');
});

function treasuryBackedPayoutPositionBalance(
    object $owner,
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

it('refuses missing Treasury payout recovery without explicit provider confirmation', function (): void {
    $provider = fakePayoutProvider();

    $this->artisan('xchange:disbursement:resume-missing-treasury', [
        'code' => 'CAMP-SAFE',
        '--json' => true,
    ])->assertFailed();

    $provider->assertNoDisbursementAttempted();
});
