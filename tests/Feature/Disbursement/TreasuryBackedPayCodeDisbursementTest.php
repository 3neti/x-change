<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Actions\Disbursement\RefurbishRejectedPayCodePayout;
use LBHurtado\XChange\Actions\Disbursement\RestoreUnsubmittedPayoutCorrection;
use LBHurtado\XChange\Actions\Funding\IssueTreasuryBackedPayCode;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Events\DisbursementConfirmed;
use LBHurtado\XChange\Events\DisbursementRejected;
use LBHurtado\XChange\Jobs\Redemption\DispatchVoucherRedemptionFeedbackJob;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\PayoutDestinationRevision;
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
        ->and(data_get($voucher->metadata, 'disbursement.status'))->toBe('completed')
        ->and(data_get($voucher->metadata, 'disbursement.requires_recovery'))->toBeFalse()
        ->and(data_get($voucher->metadata, 'disbursement.rejection_reason'))->toBeNull()
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

it('holds a provider-rejected payout for beneficiary correction without changing inventory', function (): void {
    Bus::fake([DispatchVoucherRedemptionFeedbackJob::class]);
    ['issuer' => $issuer, 'voucher' => $voucher] = treasuryBackedVoucherForPayout();
    $provider = fakePayoutProvider()->willReturnPendingResult(
        transactionId: 'NETBANK-TREASURY-REJECTED-1',
        provider: 'netbank',
    );
    $reserveBefore = treasuryBackedPayoutPositionBalance(
        $issuer,
        TreasuryPositionPurpose::PayCodeReserve,
    );
    $payableBefore = treasuryBackedPayoutPositionBalance(
        $issuer,
        TreasuryPositionPurpose::BeneficiaryPayoutPayable,
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
    $reconciliation->forceFill([
        'status' => 'failed',
        'needs_review' => false,
        'error_message' => 'AC01 (Incorrect account number)',
        'completed_at' => now(),
    ])->save();

    DisbursementRejected::dispatch($reconciliation->fresh());

    expect($result->status)->toBe('pending_review')
        ->and($reconciliation->refresh()->internal_status)->toBe('recovery_opened')
        ->and(data_get(
            $voucher->refresh()->metadata,
            'treasury.pay_code_reservation.status',
        ))->toBe('recovery_pending')
        ->and(data_get($voucher->metadata, 'disbursement.status'))->toBe('rejected')
        ->and(data_get($voucher->metadata, 'disbursement.requires_recovery'))->toBeTrue()
        ->and(treasuryBackedPayoutPositionBalance(
            $issuer,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe($reserveBefore - 2_000)
        ->and(treasuryBackedPayoutPositionBalance(
            $issuer,
            TreasuryPositionPurpose::BeneficiaryPayoutPayable,
        ))->toBe($payableBefore + 2_000)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))->toBe($inventoryBefore)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'pay_code.disbursement.rejected')
            ->where('subject_id', (string) $voucher->getKey())
            ->count())->toBe(1);

    $claim = VoucherClaim::query()->where('voucher_id', $voucher->getKey())->sole();
    expect($claim->status)->toBe('payout_rejected')
        ->and($claim->disbursed_amount_minor)->toBe(0)
        ->and($claim->failure_message)->toBe('AC01 (Incorrect account number)');
    Bus::assertDispatched(
        DispatchVoucherRedemptionFeedbackJob::class,
        fn (DispatchVoucherRedemptionFeedbackJob $job): bool => $job->outcomeFingerprint
            === 'provider-rejected:NETBANK-TREASURY-REJECTED-1',
    );

    DisbursementRejected::dispatch($reconciliation->fresh());

    expect(treasuryBackedPayoutPositionBalance(
        $issuer,
        TreasuryPositionPurpose::PayCodeReserve,
    ))->toBe($reserveBefore - 2_000)
        ->and(treasuryBackedPayoutPositionBalance(
            $issuer,
            TreasuryPositionPurpose::BeneficiaryPayoutPayable,
        ))->toBe($payableBefore + 2_000)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))->toBe($inventoryBefore)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'pay_code.disbursement.rejected')
            ->where('subject_id', (string) $voucher->getKey())
            ->count())->toBe(1);
    $provider->assertDisburseCalledTimes(1);
});

it('refurbishes the same pay code with an immutable corrected destination and settles once', function (): void {
    Bus::fake([DispatchVoucherRedemptionFeedbackJob::class]);
    ['issuer' => $issuer, 'voucher' => $voucher] = treasuryBackedVoucherForPayout();
    $provider = fakePayoutProvider()->willReturnPendingResult(
        transactionId: 'NETBANK-REFURBISH-REJECTED-1',
        provider: 'netbank',
    );
    app(SubmitPayCodeClaim::class)->handle($voucher, [
        'mobile' => '09707616025',
        'recipient_country' => 'PH',
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09707616025',
        ],
    ]);
    $originalReconciliation = DisbursementReconciliation::query()
        ->where('voucher_id', $voucher->getKey())
        ->sole();
    $originalReconciliation->forceFill([
        'status' => 'failed',
        'needs_review' => false,
        'error_message' => 'AC01 (Incorrect account number)',
        'completed_at' => now(),
    ])->save();
    DisbursementRejected::dispatch($originalReconciliation->fresh());
    $payableBefore = treasuryBackedPayoutPositionBalance(
        $issuer,
        TreasuryPositionPurpose::BeneficiaryPayoutPayable,
    );
    $inventoryBefore = (int) TreasuryInventory::query()->sum('balance_minor');
    $provider->willReturnSuccessfulResult(
        transactionId: 'NETBANK-REFURBISH-SUCCEEDED-1',
        provider: 'netbank',
    );

    $result = app(RefurbishRejectedPayCodePayout::class)->handle(
        voucher: $voucher,
        requestedBy: $issuer,
        bankCode: 'GXCHPHM2XXX',
        accountNumber: '09173011987',
        mobile: '639173011987',
    );

    expect($result)->toMatchArray([
        'success' => true,
        'pay_code' => $voucher->code,
        'claim_preserved' => true,
        'destination_version' => 1,
        'validation_status' => 'format_valid_provider_unverified',
        'provider_reference' => $voucher->code.'-R1',
        'provider_transaction_id' => 'NETBANK-REFURBISH-SUCCEEDED-1',
        'status' => 'succeeded',
    ])->and(PayoutDestinationRevision::query()->count())->toBe(1)
        ->and(DisbursementReconciliation::query()
            ->where('voucher_id', $voucher->getKey())->count())->toBe(2)
        ->and($originalReconciliation->refresh()->status)->toBe('failed')
        ->and($originalReconciliation->error_message)->toBe('AC01 (Incorrect account number)')
        ->and(data_get(
            $voucher->refresh()->metadata,
            'treasury.pay_code_reservation.status',
        ))->toBe('settled')
        ->and(treasuryBackedPayoutPositionBalance(
            $issuer,
            TreasuryPositionPurpose::BeneficiaryPayoutPayable,
        ))->toBe($payableBefore - 2_000)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))
        ->toBe($inventoryBefore - 2_000);

    $revision = PayoutDestinationRevision::query()->sole();
    $settledReconciliation = DisbursementReconciliation::query()
        ->where('voucher_id', $voucher->getKey())
        ->latest('id')
        ->firstOrFail();
    $settledClaim = VoucherClaim::query()
        ->where('voucher_id', $voucher->getKey())
        ->sole();

    expect($revision->account_number_ciphertext)->toBe('09173011987')
        ->and($revision->mobile_ciphertext)->toBe('09173011987')
        ->and($revision->account_number_masked)->toBe('*******1987')
        ->and(DB::table('x_change_payout_destination_revisions')
            ->value('account_number_ciphertext'))->not->toContain('09173011987')
        ->and((string) DB::table('disbursement_reconciliations')
            ->where('claim_type', 'payout_recovery')
            ->value('raw_request'))->not->toContain('09173011987')
        ->and($settledClaim->status)->toBe('succeeded')
        ->and(data_get($settledClaim->meta, 'disbursement.status'))->toBe('succeeded')
        ->and(data_get($settledClaim->meta, 'disbursement.reconciliation_id'))
        ->toBe($settledReconciliation->getKey())
        ->and(data_get($settledClaim->meta, 'disbursement.provider_transaction_id'))
        ->toBe('NETBANK-REFURBISH-SUCCEEDED-1');
    expect(ExecutionJournalEntry::query()
        ->where('event_type', 'pay_code.payout_destination.revised')
        ->where('subject_id', (string) $voucher->getKey())
        ->count())->toBe(1);
    $provider->assertDisburseCalledTimes(2);

    expect(fn () => app(RefurbishRejectedPayCodePayout::class)->handle(
        voucher: $voucher,
        requestedBy: $issuer,
        bankCode: 'GXCHPHM2XXX',
        accountNumber: '09173011987',
    ))->toThrow(RuntimeException::class);
    $provider->assertDisburseCalledTimes(2);
});

it('falls back to the claimant mobile and reopens correction when the provider rejects submission', function (): void {
    Bus::fake([DispatchVoucherRedemptionFeedbackJob::class]);
    ['issuer' => $issuer, 'voucher' => $voucher] = treasuryBackedVoucherForPayout();
    $provider = fakePayoutProvider()->willReturnPendingResult(
        transactionId: 'NETBANK-REFURBISH-ORIGINAL-1',
        provider: 'netbank',
    );
    app(SubmitPayCodeClaim::class)->handle($voucher, [
        'mobile' => '09707616025',
        'recipient_country' => 'PH',
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09707616025',
        ],
    ]);
    $originalReconciliation = DisbursementReconciliation::query()
        ->where('voucher_id', $voucher->getKey())
        ->sole();
    $originalReconciliation->forceFill([
        'status' => 'failed',
        'needs_review' => false,
        'error_message' => 'AC01 (Incorrect account number)',
        'completed_at' => now(),
    ])->save();
    DisbursementRejected::dispatch($originalReconciliation->fresh());
    $payableBefore = treasuryBackedPayoutPositionBalance(
        $issuer,
        TreasuryPositionPurpose::BeneficiaryPayoutPayable,
    );
    $inventoryBefore = (int) TreasuryInventory::query()->sum('balance_minor');
    $provider->willReturnFailedResult(
        provider: 'netbank',
        metadata: [
            'provider_submission_accepted' => false,
            'failure_phase' => 'provider_submission',
            'failure_code' => 'submission_not_accepted',
            'failure_message' => 'NetBank did not accept the payout submission.',
        ],
    );

    $result = app(RefurbishRejectedPayCodePayout::class)->handle(
        voucher: $voucher,
        requestedBy: $issuer,
        bankCode: 'GXCHPHM2XXX',
        accountNumber: '09853353980',
    );

    $failedRetry = DisbursementReconciliation::query()
        ->where('voucher_id', $voucher->getKey())
        ->latest('id')
        ->firstOrFail();
    $firstRevision = PayoutDestinationRevision::query()->sole();

    expect($result)->toMatchArray([
        'success' => false,
        'status' => 'failed',
        'provider_submission_accepted' => false,
        'provider_transaction_id' => null,
    ])->and($firstRevision->mobile_ciphertext)->toBe('09707616025')
        ->and($failedRetry->internal_status)->toBe('submission_failed')
        ->and($failedRetry->provider_transaction_id)->toBeNull()
        ->and($failedRetry->needs_review)->toBeFalse()
        ->and(VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())->sole()->status)
        ->toBe('payout_rejected')
        ->and(data_get(
            $voucher->refresh()->metadata,
            'treasury.pay_code_reservation.status',
        ))->toBe('recovery_pending')
        ->and(treasuryBackedPayoutPositionBalance(
            $issuer,
            TreasuryPositionPurpose::BeneficiaryPayoutPayable,
        ))->toBe($payableBefore)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))
        ->toBe($inventoryBefore)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'pay_code.payout_retry.submission_failed')
            ->where('subject_id', (string) $voucher->getKey())
            ->count())->toBe(1);
    $provider->assertDisburseCalledTimes(2);

    $this->artisan('xchange:reconcile:pending', ['--json' => true])
        ->assertSuccessful();
    expect($provider->checkStatusCallCount)->toBe(0);

    $provider->willReturnSuccessfulResult(
        transactionId: 'NETBANK-REFURBISH-SUCCEEDED-2',
        provider: 'netbank',
    );
    $secondResult = app(RefurbishRejectedPayCodePayout::class)->handle(
        voucher: $voucher->fresh(),
        requestedBy: $issuer,
        bankCode: 'GXCHPHM2XXX',
        accountNumber: '09173011987',
        mobile: '639173011987',
    );

    expect($secondResult)->toMatchArray([
        'success' => true,
        'status' => 'succeeded',
        'destination_version' => 2,
        'provider_transaction_id' => 'NETBANK-REFURBISH-SUCCEEDED-2',
    ])->and(PayoutDestinationRevision::query()->count())->toBe(2)
        ->and(VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())->sole()->status)
        ->toBe('succeeded');
    $provider->assertDisburseCalledTimes(3);
    $provider->assertLastRequest(function (PayoutRequestData $request): void {
        expect($request->mobile)->toBe('09173011987');
    });
});

it('guardedly restores an older correction with no provider transaction for explicit retry', function (): void {
    Bus::fake([DispatchVoucherRedemptionFeedbackJob::class]);
    ['issuer' => $issuer, 'voucher' => $voucher] = treasuryBackedVoucherForPayout();
    $provider = fakePayoutProvider()->willReturnPendingResult(
        transactionId: 'NETBANK-RESTORE-ORIGINAL-1',
        provider: 'netbank',
    );
    app(SubmitPayCodeClaim::class)->handle($voucher, [
        'mobile' => '09707616025',
        'recipient_country' => 'PH',
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09707616025',
        ],
    ]);
    $originalReconciliation = DisbursementReconciliation::query()
        ->where('voucher_id', $voucher->getKey())
        ->sole();
    $originalReconciliation->forceFill([
        'status' => 'failed',
        'needs_review' => false,
        'error_message' => 'AC01 (Incorrect account number)',
        'completed_at' => now(),
    ])->save();
    DisbursementRejected::dispatch($originalReconciliation->fresh());
    $payableBefore = treasuryBackedPayoutPositionBalance(
        $issuer,
        TreasuryPositionPurpose::BeneficiaryPayoutPayable,
    );
    $inventoryBefore = (int) TreasuryInventory::query()->sum('balance_minor');
    $provider->willThrow(new RuntimeException('Synthetic local persistence failure.'));

    $retry = app(RefurbishRejectedPayCodePayout::class)->handle(
        voucher: $voucher,
        requestedBy: $issuer,
        bankCode: 'GXCHPHM2XXX',
        accountNumber: '09853353980',
    );
    $unknownReconciliation = DisbursementReconciliation::query()
        ->where('voucher_id', $voucher->getKey())
        ->latest('id')
        ->firstOrFail();

    expect($retry['status'])->toBe('unknown')
        ->and($unknownReconciliation->provider_transaction_id)->toBeNull()
        ->and($unknownReconciliation->needs_review)->toBeTrue()
        ->and(VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())->sole()->status)
        ->toBe('payout_retry_pending');

    $restored = app(RestoreUnsubmittedPayoutCorrection::class)->handle(
        code: (string) $voucher->code,
        restoredBy: $issuer,
        evidenceReference: 'netbank-dashboard:no-operation:F6BG-R1',
        confirmedProviderDidNotAccept: true,
        reconciliationId: (int) $unknownReconciliation->getKey(),
    );

    expect($restored)->toMatchArray([
        'success' => true,
        'reconciliation_status' => 'failed',
        'internal_status' => 'submission_failed',
        'claim_status' => 'payout_rejected',
        'reservation_status' => 'recovery_pending',
        'provider_submission_accepted' => false,
        'provider_call_performed' => false,
        'treasury_changed' => false,
        'replayed' => false,
    ])->and($unknownReconciliation->refresh()->needs_review)->toBeFalse()
        ->and(treasuryBackedPayoutPositionBalance(
            $issuer,
            TreasuryPositionPurpose::BeneficiaryPayoutPayable,
        ))->toBe($payableBefore)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))
        ->toBe($inventoryBefore)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'pay_code.payout_retry.restored')
            ->where('subject_id', (string) $voucher->getKey())
            ->count())->toBe(1);

    $replayed = app(RestoreUnsubmittedPayoutCorrection::class)->handle(
        code: (string) $voucher->code,
        restoredBy: $issuer,
        evidenceReference: 'netbank-dashboard:no-operation:F6BG-R1',
        confirmedProviderDidNotAccept: true,
        reconciliationId: (int) $unknownReconciliation->getKey(),
    );

    expect($replayed['replayed'])->toBeTrue()
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'pay_code.payout_retry.restored')
            ->where('subject_id', (string) $voucher->getKey())
            ->count())->toBe(1)
        ->and($provider->checkStatusCallCount)->toBe(0);
});

it('blocks an invalid corrected wallet destination before a provider call', function (): void {
    Bus::fake([DispatchVoucherRedemptionFeedbackJob::class]);
    ['issuer' => $issuer, 'voucher' => $voucher] = treasuryBackedVoucherForPayout();
    $provider = fakePayoutProvider()->willReturnPendingResult(
        transactionId: 'NETBANK-REFURBISH-INVALID-1',
        provider: 'netbank',
    );
    app(SubmitPayCodeClaim::class)->handle($voucher, [
        'mobile' => '09707616025',
        'recipient_country' => 'PH',
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09707616025',
        ],
    ]);
    $reconciliation = DisbursementReconciliation::query()
        ->where('voucher_id', $voucher->getKey())
        ->sole();
    $reconciliation->forceFill([
        'status' => 'failed',
        'needs_review' => false,
        'error_message' => 'AC01 (Incorrect account number)',
        'completed_at' => now(),
    ])->save();
    DisbursementRejected::dispatch($reconciliation->fresh());

    expect(fn () => app(RefurbishRejectedPayCodePayout::class)->handle(
        voucher: $voucher,
        requestedBy: $issuer,
        bankCode: 'GXCHPHM2XXX',
        accountNumber: '12345678',
    ))->toThrow(
        ValidationException::class,
        'This wallet requires an 11-digit Philippine mobile account beginning with 09.',
    );

    $provider->assertDisburseCalledTimes(1);
    expect(PayoutDestinationRevision::query()->count())->toBe(0);
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
