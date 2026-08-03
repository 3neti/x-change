<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use Illuminate\Support\Facades\Bus;
use LBHurtado\Voucher\Models\Voucher;
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
use LBHurtado\XChange\Events\DisbursementConfirmed;
use LBHurtado\XChange\Jobs\Redemption\DispatchVoucherRedemptionFeedbackJob;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Treasury\TreasuryPayCodeAccountingService;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

it('pays a treasury-backed pay code without a legacy cash entity', function (): void {
    Bus::fake([DispatchVoucherRedemptionFeedbackJob::class]);
    ['issuer' => $issuer, 'voucher' => $voucher] = treasuryBackedVoucherForPayout();
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
        ->toBe($inventoryBefore - 2_000)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'pay_code.disbursement.settled')
            ->where('subject_id', (string) $voucher->getKey())
            ->count())->toBe(1);
    expect(VoucherClaim::query()->where('voucher_id', $voucher->getKey())->sole())
        ->status->toBe('succeeded')
        ->disbursed_amount_minor->toBe(2_000);
    $journal = ExecutionJournalEntry::query()
        ->where('event_type', 'pay_code.disbursement.settled')
        ->sole();
    expect($journal->correlation_id)->toBe('pay-code-disbursement:'.$voucher->code)
        ->and($journal->execution_id)->toBe((string) $reconciliation->getKey())
        ->and($journal->money['minor_amount'])->toBe(2_000)
        ->and($journal->money['currency'])->toBe('PHP')
        ->and($journal->payload['provider_status'])->toBe('succeeded')
        ->and($journal->payload['internal_status'])->toBe('finalized')
        ->and($journal->metadata['accounting_authority'])
        ->toBe('treasury_position_operations');

    DisbursementConfirmed::dispatch($reconciliation->fresh());

    expect(ExecutionJournalEntry::query()
        ->where('event_type', 'pay_code.disbursement.settled')
        ->where('subject_id', (string) $voucher->getKey())
        ->count())->toBe(1);
    $provider->assertDisburseCalledTimes(1);
    expect($provider->lastRequest?->amount)->toBe(20.0)
        ->and($provider->lastRequest?->bank_code)->toBe('GXCHPHM2XXX')
        ->and($provider->lastRequest?->account_number)->toBe('09173011987')
        ->and($provider->lastRequest?->external_id)->toBe((string) $voucher->getKey())
        ->and($provider->lastRequest?->external_code)->toBe($voucher->code);
});

it('journals settlement reached through scheduled provider reconciliation', function (): void {
    Bus::fake([DispatchVoucherRedemptionFeedbackJob::class]);
    ['issuer' => $issuer, 'voucher' => $voucher] = treasuryBackedVoucherForPayout();
    $provider = fakePayoutProvider()
        ->willReturnPendingResult(
            transactionId: 'NETBANK-TREASURY-PENDING-1',
            provider: 'netbank',
        )
        ->willResolveCheckStatusAsSuccessful(
            transactionId: 'NETBANK-TREASURY-PENDING-1',
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

    expect($result->status)->toBe('pending_review')
        ->and($reconciliation->status)->toBe('pending')
        ->and($reconciliation->internal_status)->not->toBe('finalized')
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'pay_code.disbursement.settled')
            ->count())->toBe(0);

    $this->artisan('xchange:reconcile:pending', ['--json' => true])
        ->assertSuccessful();

    expect($reconciliation->refresh()->status)->toBe('succeeded')
        ->and($reconciliation->internal_status)->toBe('finalized')
        ->and(data_get(
            $voucher->refresh()->metadata,
            'treasury.pay_code_reservation.status',
        ))->toBe('settled')
        ->and(treasuryBackedPayoutPositionBalance(
            $issuer,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe($reserveBefore - 2_000)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))
        ->toBe($inventoryBefore - 2_000)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'pay_code.disbursement.settled')
            ->where('subject_id', (string) $voucher->getKey())
            ->count())->toBe(1)
        ->and($provider->disburseCallCount)->toBe(1)
        ->and($provider->checkStatusCallCount)->toBe(1);

    $this->artisan('xchange:reconcile:pending', ['--json' => true])
        ->assertSuccessful();

    expect(ExecutionJournalEntry::query()
        ->where('event_type', 'pay_code.disbursement.settled')
        ->where('subject_id', (string) $voucher->getKey())
        ->count())->toBe(1)
        ->and($provider->disburseCallCount)->toBe(1)
        ->and($provider->checkStatusCallCount)->toBe(1);
});

it('retries a provider-confirmed settlement when its journal write initially fails', function (): void {
    Bus::fake([DispatchVoucherRedemptionFeedbackJob::class]);
    ['issuer' => $issuer, 'voucher' => $voucher] = treasuryBackedVoucherForPayout();
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(
        transactionId: 'NETBANK-TREASURY-JOURNAL-RETRY-1',
        provider: 'netbank',
    );
    $provider->willResolveCheckStatusAsSuccessful(
        transactionId: 'NETBANK-TREASURY-JOURNAL-RETRY-1',
        provider: 'netbank',
    );
    $realRecorder = app(ExecutionJournalRecorder::class);
    $failingRecorder = Mockery::mock(ExecutionJournalRecorder::class);
    $failingRecorder->shouldReceive('record')
        ->once()
        ->andThrow(new RuntimeException('Synthetic journal outage.'));
    $this->app->instance(ExecutionJournalRecorder::class, $failingRecorder);
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

    expect($result->status)->toBe('succeeded')
        ->and($reconciliation->status)->toBe('succeeded')
        ->and($reconciliation->internal_status)->toBe('journal_pending')
        ->and(data_get(
            $voucher->refresh()->metadata,
            'treasury.pay_code_reservation.status',
        ))->toBe('settled')
        ->and(treasuryBackedPayoutPositionBalance(
            $issuer,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe($reserveBefore - 2_000)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))
        ->toBe($inventoryBefore - 2_000)
        ->and(ExecutionJournalEntry::query()->count())->toBe(0)
        ->and($provider->disburseCallCount)->toBe(1);

    $this->app->instance(ExecutionJournalRecorder::class, $realRecorder);

    $this->artisan('xchange:reconcile:pending', ['--json' => true])
        ->assertSuccessful();

    expect($reconciliation->refresh()->internal_status)->toBe('finalized')
        ->and(data_get(
            $voucher->refresh()->metadata,
            'treasury.pay_code_reservation.status',
        ))->toBe('settled')
        ->and(treasuryBackedPayoutPositionBalance(
            $issuer,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe($reserveBefore - 2_000)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))
        ->toBe($inventoryBefore - 2_000)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'pay_code.disbursement.settled')
            ->count())->toBe(1)
        ->and($provider->disburseCallCount)->toBe(1)
        ->and($provider->checkStatusCallCount)->toBe(1);
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

it('resumes the known pre-provider reference persistence failure exactly once', function (): void {
    Bus::fake([DispatchVoucherRedemptionFeedbackJob::class]);
    ['voucher' => $voucher] = treasuryBackedVoucherForPayout();
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(
        transactionId: 'NETBANK-TREASURY-RECOVERY-1',
        provider: 'netbank',
    );
    $providerReference = $voucher->code.'-09173011987-S2';
    DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->getKey(),
        'voucher_code' => $voucher->code,
        'claim_type' => 'withdraw',
        'provider' => 'unknown',
        'provider_reference' => $providerReference,
        'status' => 'unknown',
        'internal_status' => 'recorded',
        'amount' => 20,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******1987',
        'settlement_rail' => 'INSTAPAY',
        'attempt_count' => 1,
        'attempted_at' => now(),
        'needs_review' => true,
        'review_reason' => 'Gateway outcome uncertain',
        'error_message' => 'null external_reference_code on disbursement_attempts',
    ]);

    $claim = app(SubmitPayCodeClaim::class)->handle($voucher, [
        'mobile' => '09173011987',
        'recipient_country' => 'PH',
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
        ],
    ]);

    expect($claim->claimed)->toBeTrue();
    $provider->assertNoDisbursementAttempted();

    $this->artisan('xchange:disbursement:resume-missing-treasury', [
        'code' => $voucher->code,
        '--confirm-no-provider-transfer' => true,
        '--json' => true,
    ])->assertSuccessful();

    $reconciliation = DisbursementReconciliation::query()
        ->where('voucher_id', $voucher->getKey())
        ->sole();

    expect($reconciliation->status)->toBe('succeeded')
        ->and($reconciliation->provider_transaction_id)->toBe('NETBANK-TREASURY-RECOVERY-1')
        ->and($reconciliation->needs_review)->toBeFalse()
        ->and($reconciliation->error_message)->toBeNull()
        ->and(data_get(
            $voucher->refresh()->metadata,
            'treasury.pay_code_reservation.status',
        ))->toBe('settled');
    expect(VoucherClaim::query()->where('voucher_id', $voucher->getKey())->sole())
        ->status->toBe('succeeded')
        ->disbursed_amount_minor->toBe(2_000)
        ->completed_at->not->toBeNull();
    $provider->assertDisburseCalledTimes(1);

    $this->artisan('xchange:disbursement:resume-missing-treasury', [
        'code' => $voucher->code,
        '--confirm-no-provider-transfer' => true,
        '--json' => true,
    ])->assertFailed();
    $provider->assertDisburseCalledTimes(1);
});

/**
 * @return array{issuer: object, voucher: Voucher}
 */
function treasuryBackedVoucherForPayout(): array
{
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

    return compact('issuer', 'voucher');
}
