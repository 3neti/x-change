<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Funding\CreateFundingIntent;
use LBHurtado\XChange\Actions\Funding\TransitionFundingIntent;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Data\Funding\FundingIntentTransitionData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Exceptions\FundingIntentConflict;
use LBHurtado\XChange\Exceptions\FundingIntentTransitionDenied;

beforeEach(function () {
    config()->set('x-change.funding.providers.netbank.enabled', true);
});

function transitionFundingIntentData(array $overrides = []): CreateFundingIntentData
{
    return new CreateFundingIntentData(...array_merge([
        'accountReference' => 'wallet:account-1001',
        'provider' => 'netbank',
        'expectedAmountMinor' => 25_000,
        'currency' => 'PHP',
        'idempotencyKey' => 'transition-funding-request-1001',
        'actorType' => 'App\\Models\\User',
        'actorId' => '42',
    ], $overrides));
}

it('moves a Funding Intent through guarded versioned states without crediting an Account', function () {
    $user = actingAsTestUser();
    $wallet = $user->wallet;
    $balanceBefore = (int) $wallet->balance;
    $transactionCountBefore = $wallet->transactions()->count();
    $intent = app(CreateFundingIntent::class)->handle(transitionFundingIntentData([
        'accountReference' => 'wallet:'.$wallet->getKey(),
        'actorType' => $user::class,
        'actorId' => (string) $user->getKey(),
    ]));
    $transition = app(TransitionFundingIntent::class);

    foreach ([
        [FundingIntentStatus::AwaitingFunds, 'provider_instructions_created'],
        [FundingIntentStatus::EvidenceReceived, 'provider_evidence_received'],
        [FundingIntentStatus::Verifying, 'provider_verification_started'],
        [FundingIntentStatus::Settled, 'provider_settlement_verified'],
    ] as [$status, $eventType]) {
        $intent = $transition->handle($intent, new FundingIntentTransitionData(
            status: $status,
            eventType: $eventType,
            actorType: 'system',
            actorId: 'funding-orchestrator',
            expectedVersion: $intent->version,
            evidenceReference: $status === FundingIntentStatus::Settled ? 'observation:91' : null,
            providerObservationId: $status === FundingIntentStatus::Settled ? 91 : null,
            providerTransactionId: $status === FundingIntentStatus::Settled ? 'txn-91' : null,
        ));
    }

    expect($intent->status)->toBe(FundingIntentStatus::Settled)
        ->and($intent->version)->toBe(5)
        ->and($intent->events)->toHaveCount(5)
        ->and($intent->matched_observation_id)->toBe(91)
        ->and($intent->provider_transaction_id)->toBe('txn-91')
        ->and($intent->settled_at)->not->toBeNull()
        ->and((int) $wallet->fresh()->balance)->toBe($balanceBefore)
        ->and($wallet->transactions()->count())->toBe($transactionCountBefore);
});

it('rejects invalid and stale Funding Intent transitions', function () {
    $intent = app(CreateFundingIntent::class)->handle(transitionFundingIntentData());
    $transition = app(TransitionFundingIntent::class);

    expect(fn () => $transition->handle($intent, new FundingIntentTransitionData(
        status: FundingIntentStatus::Settled,
        eventType: 'skip_verification',
        actorType: 'system',
        actorId: 'test',
        expectedVersion: 1,
    )))->toThrow(FundingIntentTransitionDenied::class);

    expect(fn () => $transition->handle($intent, new FundingIntentTransitionData(
        status: FundingIntentStatus::AwaitingFunds,
        eventType: 'provider_instructions_created',
        actorType: 'system',
        actorId: 'test',
        expectedVersion: 9,
    )))->toThrow(FundingIntentConflict::class, 'version conflict');
});

it('cannot mark a Funding Intent settled without authoritative provider evidence', function () {
    $intent = app(CreateFundingIntent::class)->handle(transitionFundingIntentData());
    $transition = app(TransitionFundingIntent::class);

    foreach ([
        [FundingIntentStatus::AwaitingFunds, 'provider_instructions_created'],
        [FundingIntentStatus::EvidenceReceived, 'provider_evidence_received'],
        [FundingIntentStatus::Verifying, 'provider_verification_started'],
    ] as [$status, $eventType]) {
        $intent = $transition->handle($intent, new FundingIntentTransitionData(
            status: $status,
            eventType: $eventType,
            actorType: 'system',
            actorId: 'funding-orchestrator',
            expectedVersion: $intent->version,
        ));
    }

    expect(fn () => $transition->handle($intent, new FundingIntentTransitionData(
        status: FundingIntentStatus::Settled,
        eventType: 'provider_settlement_verified',
        actorType: 'system',
        actorId: 'funding-orchestrator',
        expectedVersion: $intent->version,
    )))->toThrow(InvalidArgumentException::class, 'authoritative provider evidence');
});
