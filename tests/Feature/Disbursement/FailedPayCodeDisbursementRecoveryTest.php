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
use LBHurtado\XChange\Actions\Disbursement\RetryFailedPayCodeDisbursement;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Jobs\Redemption\DispatchVoucherRedemptionFeedbackJob;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Treasury\TreasuryPayCodeAccountingService;

it('retries a proven pre-provider failure and settles treasury exactly once', function () {
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
        idempotencyKey: 'register:inventory:netbank:guarded-disbursement-recovery',
        externalReference: 'resource:netbank:corporate-vca',
    ));
    $inventoryOperations->recognize(new TreasuryInventoryRecognitionData(
        operationReference: 'funding-recognition:netbank:guarded-disbursement-recovery',
        inventoryReference: 'inventory:netbank:vca-cash',
        settlementResourceReference: 'resource:netbank:corporate-vca',
        amountMinor: 5_000,
        currency: 'PHP',
        status: 'requested',
        idempotencyKey: 'funding-recognition-key:netbank:guarded-disbursement-recovery',
        externalReference: 'netbank:guarded-disbursement-recovery',
    ));
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$issuer->wallet->uuid,
        provider: 'netbank',
        amountMinor: 5_000,
        currency: 'PHP',
        evidenceReference: 'netbank:guarded-disbursement-recovery',
    );
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 5,
        overrides: [
            'metadata' => ['issuer_id' => (string) $issuer->getKey()],
        ],
    ));
    app(TreasuryPayCodeAccountingService::class)->reserve(
        accountOwner: $issuer,
        voucher: $voucher,
        connectionReference: 'netbank-primary',
        providerPrincipalMinor: 500,
        currency: 'PHP',
    );
    $metadata = (array) $voucher->refresh()->metadata;
    data_set($metadata, 'disbursement', [
        'gateway' => 'unknown',
        'transaction_id' => $voucher->code.'-09173011987',
        'status' => 'pending',
        'amount' => 5,
        'currency' => 'PHP',
        'settlement_rail' => 'INSTAPAY',
        'recipient_identifier' => '09173011987',
        'requires_reconciliation' => true,
        'metadata' => ['bank_code' => 'GXCHPHM2XXX'],
    ]);
    $voucher->forceFill([
        'metadata' => $metadata,
        'redeemed_at' => now(),
    ])->saveQuietly();
    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'redeem',
        'status' => 'redeemed',
        'requested_amount_minor' => 500,
        'disbursed_amount_minor' => 500,
        'currency' => 'PHP',
        'attempted_at' => now(),
        'completed_at' => now(),
        'meta' => ['disbursement' => ['status' => 'requested']],
    ]);
    $reference = $voucher->code.'-09173011987';
    $reconciliation = DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->getKey(),
        'voucher_code' => $voucher->code,
        'claim_type' => 'redeem',
        'provider' => 'unknown',
        'provider_reference' => $reference,
        'provider_transaction_id' => null,
        'status' => 'failed',
        'internal_status' => 'recorded',
        'amount' => 5,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******1987',
        'settlement_rail' => 'INSTAPAY',
        'attempt_count' => 1,
        'attempted_at' => now(),
        'needs_review' => false,
        'raw_request' => [
            'reference' => $reference,
            'amount' => 5,
            'account_number' => '09173011987',
            'bank_code' => 'GXCHPHM2XXX',
            'settlement_rail' => 'INSTAPAY',
            'currency' => 'PHP',
            'external_id' => (string) $voucher->getKey(),
            'external_code' => $voucher->code,
            'user_id' => $issuer->getKey(),
            'mobile' => '09173011987',
        ],
        'raw_response' => ['message' => 'Pre-provider compatibility failure'],
        'meta' => ['flow' => 'redeem', 'slice_number' => 1],
    ]);
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(
        transactionId: 'NETBANK-RECOVERY-1',
        provider: 'netbank',
    );
    $reserveBefore = recoveryPositionBalance(
        $issuer,
        TreasuryPositionPurpose::PayCodeReserve,
    );
    $inventoryBefore = (int) TreasuryInventory::query()->sum('balance_minor');

    $result = app(RetryFailedPayCodeDisbursement::class)->handle(
        $voucher->code,
        true,
    );

    expect($result)->toMatchArray([
        'success' => true,
        'pay_code' => $voucher->code,
        'provider_reference' => $reference,
        'provider_transaction_id' => 'NETBANK-RECOVERY-1',
        'provider' => 'netbank',
        'status' => 'succeeded',
        'attempt_count' => 2,
    ])
        ->and($provider->lastRequest?->reference)->toBe($reference)
        ->and(recoveryPositionBalance(
            $issuer,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe($reserveBefore - 500)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))
        ->toBe($inventoryBefore - 500)
        ->and($reconciliation->refresh()->internal_status)->toBe('finalized')
        ->and(data_get(
            $voucher->refresh()->metadata,
            'treasury.pay_code_reservation.status',
        ))->toBe('settled')
        ->and(data_get(
            $voucher->metadata,
            'disbursement.requires_reconciliation',
        ))->toBeFalse();
    $provider->assertDisburseCalledTimes(1);
    Bus::assertDispatched(
        DispatchVoucherRedemptionFeedbackJob::class,
        fn (DispatchVoucherRedemptionFeedbackJob $job): bool => $job->outcomeFingerprint
            === 'provider-confirmed:NETBANK-RECOVERY-1',
    );

    expect(fn () => app(RetryFailedPayCodeDisbursement::class)->handle(
        $voucher->code,
        true,
    ))->toThrow(RuntimeException::class);
    $provider->assertDisburseCalledTimes(1);
});

it('does not call the provider without explicit no-transfer confirmation', function () {
    $provider = fakePayoutProvider();

    $this->artisan('xchange:disbursement:retry', [
        'code' => 'ANY-CODE',
        '--json' => true,
    ])->assertFailed();

    $provider->assertNoDisbursementAttempted();
});

function recoveryPositionBalance(
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
