<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Transaction;
use Bavix\Wallet\Models\Wallet;
use Illuminate\Support\Str;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\Wallet\Treasury\Models\TreasurySettlementResource;
use LBHurtado\XChange\Actions\Funding\ApproveFundingReconciliation;
use LBHurtado\XChange\Actions\Funding\OpenFundingSuspenseCase;
use LBHurtado\XChange\Actions\Funding\RequestFundingReconciliation;
use LBHurtado\XChange\Actions\Funding\ReverseSettledFundingIntent;
use LBHurtado\XChange\Actions\Funding\SettleVerifiedFundingIntent;
use LBHurtado\XChange\Actions\Funding\TransitionFundingIntent;
use LBHurtado\XChange\Contracts\ProviderFundingPolicyContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Data\Funding\FundingIntentTransitionData;
use LBHurtado\XChange\Data\Treasury\VerifiedTreasuryFundingAllocationData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\FundingReconciliationAction;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Exceptions\InsufficientWalletBalance;
use LBHurtado\XChange\Models\FundingAccountHold;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingReconciliationRequest;
use LBHurtado\XChange\Models\FundingRecovery;
use LBHurtado\XChange\Models\FundingRecoveryPayment;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\FundingSuspenseCase;

beforeEach(function () {
    enableNetbankTreasuryForTests();
});

it('atomically recognizes verified net inventory and credits the Account once', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $observation = providerFundingObservationForSettlement();
    $intent = verifiedFundingIntentForSettlement($wallet, $observation);

    $first = app(SettleVerifiedFundingIntent::class)->handle($intent);
    $second = app(SettleVerifiedFundingIntent::class)->handle($intent->refresh());

    expect($second->is($first))->toBeTrue()
        ->and($intent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0)
        ->and(treasuryClientFundsLedger($user)->getBalanceIntAttribute())->toBe(24_950)
        ->and($first->gross_amount_minor)->toBe(25_000)
        ->and($first->fee_amount_minor)->toBe(50)
        ->and($first->net_amount_minor)->toBe(24_950)
        ->and($first->treasury_inventory_reference)->toBe('inventory:netbank:vca-cash')
        ->and(FundingSettlement::query()->count())->toBe(1)
        ->and(TreasurySettlementResource::query()->count())->toBe(1)
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(24_950)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(1)
        ->and(TreasuryPositionOperation::query()->count())->toBe(2);

    $transaction = Transaction::query()->findOrFail($first->wallet_transaction_id);

    expect($transaction->meta)->toMatchArray([
        'source' => 'verified_provider_funding',
        'provider' => 'netbank',
        'provider_transaction_id' => $observation->provider_transaction_id,
    ]);
});

it('rolls back Treasury recognition when the Account credit fails', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $observation = providerFundingObservationForSettlement();
    $intent = verifiedFundingIntentForSettlement($wallet, $observation);

    app()->instance(VerifiedTreasuryFundingAllocationContract::class, new class implements VerifiedTreasuryFundingAllocationContract
    {
        public function allocate(
            string $accountReference,
            string $provider,
            int $amountMinor,
            string $currency,
            string $evidenceReference,
            array $metadata = [],
        ): VerifiedTreasuryFundingAllocationData {
            throw new RuntimeException('simulated Treasury allocation failure');
        }
    });

    expect(fn () => app(SettleVerifiedFundingIntent::class)->handle($intent))
        ->toThrow(RuntimeException::class, 'simulated Treasury allocation failure');

    expect($intent->refresh()->status)->toBe(FundingIntentStatus::Verified)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0)
        ->and(FundingSettlement::query()->count())->toBe(0)
        ->and(TreasurySettlementResource::query()->count())->toBe(0)
        ->and(TreasuryInventory::query()->count())->toBe(0)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(0);
});

it('rejects a verified intent when its authoritative evidence no longer matches', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $observation = providerFundingObservationForSettlement([
        'metadata' => ['destination_verified' => false],
    ]);
    $intent = verifiedFundingIntentForSettlement($wallet, $observation);

    expect(fn () => app(SettleVerifiedFundingIntent::class)->handle($intent))
        ->toThrow(FundingSettlementDenied::class, 'authoritative provider evidence no longer matches');

    expect($intent->refresh()->status)->toBe(FundingIntentStatus::Verified)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0)
        ->and(FundingSettlement::query()->count())->toBe(0)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(0);
});

it('reverses Treasury Inventory and fully recovers an unspent Account credit once', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $settledObservation = providerFundingObservationForSettlement();
    $intent = verifiedFundingIntentForSettlement($wallet, $settledObservation);
    app(SettleVerifiedFundingIntent::class)->handle($intent);
    $reversalObservation = providerFundingReversalObservation($settledObservation);

    $first = app(ReverseSettledFundingIntent::class)->handle($intent->refresh(), $reversalObservation);
    $second = app(ReverseSettledFundingIntent::class)->handle($intent->refresh(), $reversalObservation);

    expect($second->is($first))->toBeTrue()
        ->and($intent->refresh()->status)->toBe(FundingIntentStatus::Reversed)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0)
        ->and(treasuryClientFundsLedger($user)->getBalanceIntAttribute())->toBe(0)
        ->and($first->reversal_amount_minor)->toBe(24_950)
        ->and($first->recovered_amount_minor)->toBe(24_950)
        ->and($first->outstanding_amount_minor)->toBe(0)
        ->and($first->status)->toBe('recovered')
        ->and(FundingRecovery::query()->count())->toBe(1)
        ->and(FundingAccountHold::query()->count())->toBe(0)
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(0)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(2);
});

it('recovers available funds and freezes issuance for a reversal deficit', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $settledObservation = providerFundingObservationForSettlement();
    $intent = verifiedFundingIntentForSettlement($wallet, $settledObservation);
    app(SettleVerifiedFundingIntent::class)->handle($intent);
    treasuryClientFundsLedger($user)->withdraw(20_000, ['source' => 'simulated_spend']);

    $recovery = app(ReverseSettledFundingIntent::class)->handle(
        $intent->refresh(),
        providerFundingReversalObservation($settledObservation),
    );
    $hold = FundingAccountHold::query()->sole();

    expect($recovery->recovered_amount_minor)->toBe(4_950)
        ->and($recovery->outstanding_amount_minor)->toBe(20_000)
        ->and($recovery->status)->toBe('open')
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0)
        ->and(treasuryClientFundsLedger($user)->getBalanceIntAttribute())->toBe(0)
        ->and($hold->account_reference)->toBe('wallet:'.$wallet->uuid)
        ->and($hold->outstanding_amount_minor)->toBe(20_000)
        ->and($hold->status)->toBe('active')
        ->and(fn () => app(ProviderFundingPolicyContract::class)->assertCanIssue(
            $user,
            $wallet,
            1,
        ))->toThrow(InsufficientWalletBalance::class, 'Account issuance is frozen');
});

it('rejects mismatched reversal evidence without changing Inventory or Account balance', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $settledObservation = providerFundingObservationForSettlement();
    $intent = verifiedFundingIntentForSettlement($wallet, $settledObservation);
    app(SettleVerifiedFundingIntent::class)->handle($intent);
    $reversalObservation = providerFundingReversalObservation($settledObservation, [
        'net_amount_minor' => 20_000,
    ]);

    expect(fn () => app(ReverseSettledFundingIntent::class)->handle(
        $intent->refresh(),
        $reversalObservation,
    ))->toThrow(FundingSettlementDenied::class, 'reversal evidence does not match');

    expect($intent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0)
        ->and(treasuryClientFundsLedger($user)->getBalanceIntAttribute())->toBe(24_950)
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(24_950)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(1)
        ->and(FundingRecovery::query()->count())->toBe(0);
});

it('applies later verified funding to an outstanding recovery and releases the hold', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $firstObservation = providerFundingObservationForSettlement();
    $firstIntent = verifiedFundingIntentForSettlement($wallet, $firstObservation);
    app(SettleVerifiedFundingIntent::class)->handle($firstIntent);
    treasuryClientFundsLedger($user)->withdraw(20_000, ['source' => 'simulated_spend']);
    app(ReverseSettledFundingIntent::class)->handle(
        $firstIntent->refresh(),
        providerFundingReversalObservation($firstObservation),
    );

    $secondObservation = providerFundingObservationForSettlement();
    $secondIntent = verifiedFundingIntentForSettlement($wallet, $secondObservation);
    app(SettleVerifiedFundingIntent::class)->handle($secondIntent);

    $recovery = FundingRecovery::query()->sole();
    $hold = FundingAccountHold::query()->sole();
    $payment = FundingRecoveryPayment::query()->sole();

    expect($recovery->recovered_amount_minor)->toBe(24_950)
        ->and($recovery->outstanding_amount_minor)->toBe(0)
        ->and($recovery->status)->toBe('recovered')
        ->and($hold->outstanding_amount_minor)->toBe(0)
        ->and($hold->status)->toBe('released')
        ->and($hold->released_by_type)->toBe('funding_recovery_runtime')
        ->and($payment->amount_minor)->toBe(20_000)
        ->and($payment->funding_settlement_id)->toBe($secondIntent->settlement()->sole()->getKey())
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0)
        ->and(treasuryClientFundsLedger($user)->getBalanceIntAttribute())->toBe(4_950)
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(24_950)
        ->and(FundingAccountHold::query()->where('status', 'active')->count())->toBe(0);
});

it('requires a second operator before matching exact provider evidence and settling', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $observation = providerFundingObservationForSettlement();
    $intent = verifiedFundingIntentForSettlement($wallet, $observation);
    $intent = app(TransitionFundingIntent::class)->handle($intent, new FundingIntentTransitionData(
        status: FundingIntentStatus::Suspense,
        eventType: 'provider_evidence_requires_review',
        actorType: 'verification_runtime',
        actorId: 'netbank',
        expectedVersion: $intent->version,
        evidenceReference: 'provider-observation:'.$observation->getKey(),
    ));
    $case = app(OpenFundingSuspenseCase::class)->handle(
        provider: 'netbank',
        reasonCode: 'provider_evidence_requires_review',
        intent: $intent,
        observation: $observation,
    );
    $request = app(RequestFundingReconciliation::class)->handle(
        case: $case,
        action: FundingReconciliationAction::MatchVerifiedObservation,
        actorType: 'operator',
        actorId: 'maker-1',
    );

    expect(fn () => app(ApproveFundingReconciliation::class)->handle(
        $request,
        'operator',
        'maker-1',
    ))->toThrow(InvalidArgumentException::class, 'cannot approve their own request')
        ->and($request->refresh()->status)->toBe('pending_approval')
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0);

    $approved = app(ApproveFundingReconciliation::class)->handle(
        $request,
        'operator',
        'checker-2',
    );

    expect($approved->status)->toBe('executed')
        ->and($approved->requested_by_id)->toBe('maker-1')
        ->and($approved->approved_by_id)->toBe('checker-2')
        ->and($approved->result)->toMatchArray([
            'outcome' => 'observation_matched_and_settled',
        ])
        ->and($intent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0)
        ->and(treasuryClientFundsLedger($user)->getBalanceIntAttribute())->toBe(24_950)
        ->and(FundingSettlement::query()->count())->toBe(1)
        ->and(FundingSuspenseCase::query()->sole()->status)->toBe('resolved')
        ->and(FundingSuspenseCase::query()->sole()->resolved_by_id)->toBe('checker-2')
        ->and(fn () => FundingReconciliationRequest::query()->sole()->delete())
        ->toThrow(LogicException::class, 'cannot be deleted');
});

it('dual-controls compensation by retrying only an already verified posting', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $observation = providerFundingObservationForSettlement();
    $intent = verifiedFundingIntentForSettlement($wallet, $observation);
    $case = app(OpenFundingSuspenseCase::class)->handle(
        provider: 'netbank',
        reasonCode: 'verified_posting_failed',
        intent: $intent,
        observation: $observation,
    );
    $request = app(RequestFundingReconciliation::class)->handle(
        case: $case,
        action: FundingReconciliationAction::CompensateVerifiedPosting,
        actorType: 'operator',
        actorId: 'maker-1',
    );

    $approved = app(ApproveFundingReconciliation::class)->handle(
        $request,
        'operator',
        'checker-2',
    );

    expect($approved->result['outcome'])->toBe('verified_posting_compensated')
        ->and($intent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0)
        ->and(treasuryClientFundsLedger($user)->getBalanceIntAttribute())->toBe(24_950)
        ->and(FundingSettlement::query()->count())->toBe(1)
        ->and($case->refresh()->status)->toBe('resolved');
});

/**
 * @param  array<string, mixed>  $overrides
 */
function providerFundingObservationForSettlement(array $overrides = []): ProviderFundingObservation
{
    $transactionId = 'NB-'.Str::upper(Str::random(12));

    return ProviderFundingObservation::query()->create(array_replace([
        'observation_key' => hash('sha256', $transactionId),
        'provider_code' => 'netbank',
        'provider_transaction_id' => $transactionId,
        'provider_operation_id' => 'OP-'.$transactionId,
        'request_id' => 'REQ-'.$transactionId,
        'funding_address' => '001234567890',
        'provider_account_reference' => 'corporate-vca',
        'gross_amount_minor' => 25_000,
        'fee_amount_minor' => 50,
        'net_amount_minor' => 24_950,
        'currency' => 'PHP',
        'provider_status' => 'settled',
        'occurred_at' => now()->subMinute(),
        'settled_at' => now(),
        'verification_source' => 'transaction_history',
        'payload_hash' => hash('sha256', 'payload-'.$transactionId),
        'metadata' => ['destination_verified' => true],
    ], $overrides));
}

function verifiedFundingIntentForSettlement(
    Wallet $wallet,
    ProviderFundingObservation $observation,
): FundingIntent {
    $idempotency = (string) Str::uuid();

    return FundingIntent::query()->create([
        'account_reference' => 'wallet:'.$wallet->uuid,
        'provider_code' => 'netbank',
        'expected_amount_minor' => 25_000,
        'currency' => 'PHP',
        'status' => FundingIntentStatus::Verified,
        'version' => 4,
        'idempotency_key_hash' => hash('sha256', $idempotency),
        'idempotency_fingerprint' => hash('sha256', 'fingerprint-'.$idempotency),
        'created_by_type' => 'test',
        'created_by_id' => 'operator-1',
        'provider_reference' => 'VCA-'.$wallet->uuid,
        'provider_request_id' => $observation->request_id,
        'funding_address_ciphertext' => $observation->funding_address,
        'funding_address_hash' => hash('sha256', (string) $observation->funding_address),
        'matched_observation_id' => $observation->getKey(),
        'provider_transaction_id' => $observation->provider_transaction_id,
        'instructions_created_at' => now()->subMinutes(2),
        'evidence_received_at' => now()->subMinute(),
        'verified_at' => now(),
        'expires_at' => now()->addMinutes(30),
        'metadata' => ['source' => 'test'],
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function providerFundingReversalObservation(
    ProviderFundingObservation $settledObservation,
    array $overrides = [],
): ProviderFundingObservation {
    return ProviderFundingObservation::query()->create(array_replace([
        'observation_key' => hash('sha256', 'reversal-'.$settledObservation->provider_transaction_id),
        'provider_code' => $settledObservation->provider_code,
        'provider_transaction_id' => $settledObservation->provider_transaction_id,
        'provider_operation_id' => 'REV-'.$settledObservation->provider_transaction_id,
        'request_id' => $settledObservation->request_id,
        'funding_address' => $settledObservation->funding_address,
        'provider_account_reference' => $settledObservation->provider_account_reference,
        'gross_amount_minor' => $settledObservation->gross_amount_minor,
        'fee_amount_minor' => $settledObservation->fee_amount_minor,
        'net_amount_minor' => $settledObservation->net_amount_minor,
        'currency' => $settledObservation->currency,
        'provider_status' => 'reversed',
        'occurred_at' => now(),
        'settled_at' => null,
        'verification_source' => 'transaction_history',
        'payload_hash' => hash('sha256', 'reversal-payload-'.$settledObservation->provider_transaction_id),
        'metadata' => ['destination_verified' => true],
    ], $overrides));
}
