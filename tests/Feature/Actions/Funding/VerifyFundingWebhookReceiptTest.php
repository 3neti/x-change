<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Queue;
use LBHurtado\EmiCore\Actions\Funding\StoreProviderWebhookReceipt;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Data\Funding\ProviderEventHintData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookRequestData;
use LBHurtado\EmiCore\Data\Funding\WebhookAuthenticationData;
use LBHurtado\EmiCore\Exceptions\ProviderFundingNotObserved;
use LBHurtado\EmiCore\Exceptions\ProviderFundingVerificationIndeterminate;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\EmiCore\Models\WebhookReceipt;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\XChange\Actions\Funding\ApproveFundingReconciliation;
use LBHurtado\XChange\Actions\Funding\CreateFundingIntent;
use LBHurtado\XChange\Actions\Funding\IssueFundingInstructions;
use LBHurtado\XChange\Actions\Funding\RequestFundingReconciliation;
use LBHurtado\XChange\Actions\Funding\VerifyFundingIntent;
use LBHurtado\XChange\Actions\Funding\VerifyFundingWebhookReceipt;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Data\Funding\FundingIntentVerificationData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\FundingReconciliationAction;
use LBHurtado\XChange\Enums\FundingVerificationTrigger;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingIntentJob;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingWebhookReceiptJob;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingRecovery;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\FundingSuspenseCase;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;

beforeEach(function () {
    config()->set('x-change.funding.providers.netbank.enabled', true);
    $this->fundingAdapter = new FakeFundingProviderAdapter;
    $this->app->instance(FakeFundingProviderAdapter::class, $this->fundingAdapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
});

it('records authoritative evidence and stops at verified without crediting the Account', function () {
    $user = actingAsTestUser();
    $wallet = $user->wallet;
    $balanceBefore = (int) $wallet->balance;
    $transactionCountBefore = $wallet->transactions()->count();
    $intent = verifiedFundingIntent('wallet:'.$wallet->getKey());
    $receipt = authenticatedFundingReceipt();

    $processed = app(VerifyFundingWebhookReceipt::class)->handle($receipt);
    $intent = $intent->fresh()->load('events');
    $observation = ProviderFundingObservation::query()->sole();

    expect($processed)->toBe(1)
        ->and($intent->status)->toBe(FundingIntentStatus::Verified)
        ->and($intent->version)->toBe(5)
        ->and($intent->verified_at)->not->toBeNull()
        ->and($intent->settled_at)->toBeNull()
        ->and($intent->matched_observation_id)->toBe($observation->getKey())
        ->and($intent->provider_transaction_id)->toBe('provider-transaction-123')
        ->and($observation->gross_amount_minor)->toBe(25_000)
        ->and($observation->net_amount_minor)->toBe(24_950)
        ->and($receipt->fresh()->processing_status)->toBe('processed')
        ->and($intent->events->pluck('event_type')->all())->toBe([
            'created',
            'provider_instructions_created',
            'provider_evidence_received',
            'provider_verification_started',
            'provider_settlement_verified',
        ])
        ->and((int) $wallet->fresh()->balance)->toBe($balanceBefore)
        ->and($wallet->transactions()->count())->toBe($transactionCountBefore);
});

it('settles authoritative evidence through the retryable verification job', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $intent = verifiedFundingIntent('wallet:'.$wallet->uuid);
    $receipt = authenticatedFundingReceipt('queued-settlement');

    app()->call([new VerifyFundingWebhookReceiptJob($receipt->getKey()), 'handle']);
    app()->call([new VerifyFundingWebhookReceiptJob($receipt->getKey()), 'handle']);

    expect($intent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(24_950)
        ->and(FundingSettlement::query()->count())->toBe(1)
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(24_950)
        ->and($receipt->refresh()->processing_status)->toBe('processed');
});

it('recovers settlement after verification completed on an earlier attempt', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $intent = verifiedFundingIntent('wallet:'.$wallet->uuid);
    $receipt = authenticatedFundingReceipt('settlement-recovery');

    app(VerifyFundingWebhookReceipt::class)->handle($receipt);

    expect($intent->refresh()->status)->toBe(FundingIntentStatus::Verified)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0);

    app()->call([new VerifyFundingWebhookReceiptJob($receipt->getKey()), 'handle']);

    expect($intent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(24_950)
        ->and(FundingSettlement::query()->count())->toBe(1);
});

it('re-queries settled funding and recovers an authoritative provider reversal', function () {
    $user = actingAsTestUser();
    $wallet = $user->wallet;
    $balanceBefore = (int) $wallet->balanceInt;
    $intent = verifiedFundingIntent('wallet:'.$wallet->getKey());

    app()->call([
        new VerifyFundingWebhookReceiptJob(authenticatedFundingReceipt('initial-settlement')->getKey()),
        'handle',
    ]);

    expect($intent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and((int) $wallet->refresh()->balanceInt)->toBe($balanceBefore + 24_950);

    $this->fundingAdapter->fundingObservation = fundingObservation([
        'providerStatus' => 'reversed',
        'occurredAt' => new DateTimeImmutable,
        'settledAt' => null,
        'payloadHash' => hash('sha256', 'provider-reversal-observation'),
    ]);
    $reversalReceipt = authenticatedFundingReceipt('provider-reversal');

    app()->call([new VerifyFundingWebhookReceiptJob($reversalReceipt->getKey()), 'handle']);

    expect($intent->refresh()->status)->toBe(FundingIntentStatus::Reversed)
        ->and((int) $wallet->refresh()->balanceInt)->toBe($balanceBefore)
        ->and(FundingRecovery::query()->sole()->status)->toBe('recovered')
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(0)
        ->and($reversalReceipt->refresh()->processing_status)->toBe('processed');
});

it('returns an intent to awaiting funds when the provider has not observed a transfer', function () {
    $this->fundingAdapter = new class extends FakeFundingProviderAdapter
    {
        public function verifyFunding(FundingVerificationData $verification): ProviderFundingObservationData
        {
            throw new ProviderFundingNotObserved('No transfer observed.');
        }
    };
    replaceFundingAdapter($this->fundingAdapter);
    $intent = verifiedFundingIntent();

    app(VerifyFundingWebhookReceipt::class)->handle(authenticatedFundingReceipt('not-observed'));

    expect($intent->fresh()->status)->toBe(FundingIntentStatus::AwaitingFunds)
        ->and(ProviderFundingObservation::query()->count())->toBe(0);
});

it('moves indeterminate provider evidence to suspense', function () {
    $this->fundingAdapter = new class extends FakeFundingProviderAdapter
    {
        public function verifyFunding(FundingVerificationData $verification): ProviderFundingObservationData
        {
            throw new ProviderFundingVerificationIndeterminate('Ambiguous provider evidence.');
        }
    };
    replaceFundingAdapter($this->fundingAdapter);
    $intent = verifiedFundingIntent();

    app(VerifyFundingWebhookReceipt::class)->handle(authenticatedFundingReceipt('indeterminate'));

    expect($intent->fresh()->status)->toBe(FundingIntentStatus::Suspense)
        ->and(ProviderFundingObservation::query()->count())->toBe(0)
        ->and(FundingSuspenseCase::query()->sole()->reason_code)
        ->toBe('provider_verification_indeterminate');
});

it('moves settled amount or currency mismatches to suspense', function () {
    $this->fundingAdapter->fundingObservation = fundingObservation([
        'grossAmountMinor' => 24_000,
        'netAmountMinor' => 23_950,
    ]);
    $intent = verifiedFundingIntent();

    app(VerifyFundingWebhookReceipt::class)->handle(authenticatedFundingReceipt('mismatch'));

    expect($intent->fresh()->status)->toBe(FundingIntentStatus::Suspense)
        ->and(ProviderFundingObservation::query()->sole()->gross_amount_minor)->toBe(24_000)
        ->and(FundingSuspenseCase::query()->sole()->details)->toMatchArray([
            'provider_status' => 'settled',
            'gross_amount_minor' => 24_000,
            'net_amount_minor' => 23_950,
            'currency' => 'PHP',
            'destination_verified' => true,
        ]);
});

it('returns pending observations to awaiting funds', function () {
    $this->fundingAdapter->fundingObservation = fundingObservation([
        'providerStatus' => 'pending',
        'settledAt' => null,
    ]);
    $intent = verifiedFundingIntent();

    app(VerifyFundingWebhookReceipt::class)->handle(authenticatedFundingReceipt('pending'));

    expect($intent->fresh()->status)->toBe(FundingIntentStatus::AwaitingFunds);
});

it('verifies direct provider checks without fabricating webhook evidence', function (
    FundingVerificationTrigger $trigger,
) {
    $intent = verifiedFundingIntent();

    $verified = app(VerifyFundingIntent::class)->handle($intent, new FundingIntentVerificationData(
        trigger: $trigger,
        actorId: $trigger === FundingVerificationTrigger::Operator ? 'operator-42' : 'scheduler',
    ));

    expect($verified->status)->toBe(FundingIntentStatus::Verified)
        ->and($this->fundingAdapter->lastVerification?->webhookReceiptId)->toBeNull()
        ->and($verified->events->pluck('event_type')->all())->toBe([
            'created',
            'provider_instructions_created',
            'provider_verification_started',
            'provider_settlement_verified',
        ])
        ->and($verified->events->pluck('metadata.trigger')->filter()->unique()->values()->all())
        ->toBe([$trigger->value]);
})->with([
    'operator' => FundingVerificationTrigger::Operator,
    'schedule' => FundingVerificationTrigger::Schedule,
]);

it('settles once when direct verification jobs are replayed', function (
    FundingVerificationTrigger $trigger,
) {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $intent = verifiedFundingIntent('wallet:'.$wallet->uuid);
    $job = new VerifyFundingIntentJob(
        fundingIntentId: $intent->getKey(),
        providerCode: 'netbank',
        trigger: $trigger,
        actorId: $trigger === FundingVerificationTrigger::Operator ? 'operator-42' : 'scheduler',
    );

    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    expect($intent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(24_950)
        ->and(FundingSettlement::query()->count())->toBe(1)
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(24_950);
})->with([
    'operator' => FundingVerificationTrigger::Operator,
    'schedule' => FundingVerificationTrigger::Schedule,
]);

it('returns provider outages to awaiting funds with only a sanitized failure event', function () {
    $this->fundingAdapter = new class extends FakeFundingProviderAdapter
    {
        public function verifyFunding(FundingVerificationData $verification): ProviderFundingObservationData
        {
            throw new RuntimeException('secret provider response and account 113001000019');
        }
    };
    replaceFundingAdapter($this->fundingAdapter);
    $intent = verifiedFundingIntent();

    $checked = app(VerifyFundingIntent::class)->handle($intent, new FundingIntentVerificationData(
        trigger: FundingVerificationTrigger::Operator,
        actorId: 'operator-42',
    ));
    $event = $checked->events->last();

    expect($checked->status)->toBe(FundingIntentStatus::AwaitingFunds)
        ->and($event?->event_type)->toBe('provider_verification_unavailable')
        ->and($event?->metadata)->toMatchArray([
            'trigger' => 'operator',
            'provider' => 'netbank',
            'failure_type' => 'RuntimeException',
        ])
        ->and(json_encode($event?->metadata))->not->toContain('secret')
        ->not->toContain('113001000019');
});

it('makes provider verification jobs unique, non-overlapping, and provider rate limited', function () {
    $intent = verifiedFundingIntent();
    $operatorJob = new VerifyFundingIntentJob(
        fundingIntentId: $intent->getKey(),
        providerCode: 'netbank',
        trigger: FundingVerificationTrigger::Operator,
        actorId: 'operator-42',
    );
    $scheduledJob = new VerifyFundingIntentJob(
        fundingIntentId: $intent->getKey(),
        providerCode: 'netbank',
        trigger: FundingVerificationTrigger::Schedule,
        actorId: 'scheduler',
    );

    expect($operatorJob)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($operatorJob->uniqueId())->toBe($scheduledJob->uniqueId())
        ->and($operatorJob->middleware()[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($operatorJob->middleware()[1])->toBeInstanceOf(RateLimited::class);
});

it('moves a mismatched direct provider check to suspense without a webhook receipt', function () {
    $this->fundingAdapter->fundingObservation = fundingObservation([
        'grossAmountMinor' => 24_000,
        'netAmountMinor' => 23_950,
    ]);
    $intent = verifiedFundingIntent();

    $checked = app(VerifyFundingIntent::class)->handle($intent, new FundingIntentVerificationData(
        trigger: FundingVerificationTrigger::Operator,
        actorId: 'operator-42',
    ));
    $case = FundingSuspenseCase::query()->sole();

    expect($checked->status)->toBe(FundingIntentStatus::Suspense)
        ->and($case->webhook_receipt_id)->toBeNull()
        ->and($case->reason_code)->toBe('provider_evidence_mismatch');
});

it('marks authenticated evidence unmatched when no active intent exists', function () {
    $receipt = authenticatedFundingReceipt('unmatched');

    expect(app(VerifyFundingWebhookReceipt::class)->handle($receipt))->toBe(0)
        ->and(app(VerifyFundingWebhookReceipt::class)->handle($receipt))->toBe(0)
        ->and($receipt->fresh()->processing_status)->toBe('unmatched')
        ->and(ProviderFundingObservation::query()->count())->toBe(0)
        ->and(FundingSuspenseCase::query()->count())->toBe(1)
        ->and(FundingSuspenseCase::query()->sole()->reason_code)
        ->toBe('authenticated_evidence_unmatched')
        ->and(FundingSuspenseCase::query()->sole()->details)->toBe([
            'webhook_receipt_id' => $receipt->getKey(),
        ]);
});

it('prevents suspense review evidence from being rewritten or deleted directly', function () {
    app(VerifyFundingWebhookReceipt::class)->handle(authenticatedFundingReceipt('guarded-suspense'));
    $case = FundingSuspenseCase::query()->sole();

    expect(fn () => $case->update(['reason_code' => 'tampered']))
        ->toThrow(LogicException::class, 'guarded review actions')
        ->and(fn () => $case->delete())
        ->toThrow(LogicException::class, 'cannot be deleted');
});

it('requires dual control before queueing a preserved-evidence verification retry', function () {
    $operator = actingAsTestUser(0);
    $wallet = $operator->wallet()->where('slug', 'platform')->firstOrFail();
    $this->fundingAdapter = new class extends FakeFundingProviderAdapter
    {
        public function verifyFunding(FundingVerificationData $verification): ProviderFundingObservationData
        {
            throw new ProviderFundingVerificationIndeterminate('Ambiguous provider evidence.');
        }
    };
    replaceFundingAdapter($this->fundingAdapter);
    $intent = verifiedFundingIntent('wallet:'.$wallet->uuid);
    $receipt = authenticatedFundingReceipt('dual-control-retry');
    app(VerifyFundingWebhookReceipt::class)->handle($receipt);
    $case = FundingSuspenseCase::query()->sole();
    $request = app(RequestFundingReconciliation::class)->handle(
        case: $case,
        action: FundingReconciliationAction::RetryVerification,
        actorType: 'operator',
        actorId: 'maker-1',
    );
    Queue::fake();

    $approved = app(ApproveFundingReconciliation::class)->handle(
        $request,
        'operator',
        'checker-2',
    );

    expect($approved->status)->toBe('executed')
        ->and($approved->result['outcome'])->toBe('verification_retry_queued')
        ->and($intent->refresh()->status)->toBe(FundingIntentStatus::Verifying)
        ->and($receipt->refresh()->processing_status)->toBe('received')
        ->and($case->refresh()->status)->toBe('monitoring');

    Queue::assertPushed(
        VerifyFundingWebhookReceiptJob::class,
        fn (VerifyFundingWebhookReceiptJob $job): bool => $job->webhookReceiptId === $receipt->getKey(),
    );

    $this->fundingAdapter = new FakeFundingProviderAdapter;
    replaceFundingAdapter($this->fundingAdapter);
    app()->call([new VerifyFundingWebhookReceiptJob($receipt->getKey()), 'handle']);

    expect($intent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(24_950)
        ->and($case->refresh()->status)->toBe('resolved')
        ->and($case->resolution_code)->toBe('verification_retry_settled')
        ->and($case->resolution)->toBe([
            'funding_intent_status' => 'settled',
        ]);
});

it('closes monitoring when an approved retry confirms funds are still not observed', function () {
    $this->fundingAdapter = new class extends FakeFundingProviderAdapter
    {
        public function verifyFunding(FundingVerificationData $verification): ProviderFundingObservationData
        {
            throw new ProviderFundingVerificationIndeterminate('Ambiguous provider evidence.');
        }
    };
    replaceFundingAdapter($this->fundingAdapter);
    $intent = verifiedFundingIntent();
    $receipt = authenticatedFundingReceipt('dual-control-not-observed');
    app(VerifyFundingWebhookReceipt::class)->handle($receipt);
    $case = FundingSuspenseCase::query()->sole();
    $request = app(RequestFundingReconciliation::class)->handle(
        case: $case,
        action: FundingReconciliationAction::RetryVerification,
        actorType: 'operator',
        actorId: 'maker-1',
    );
    Queue::fake();
    app(ApproveFundingReconciliation::class)->handle($request, 'operator', 'checker-2');

    $this->fundingAdapter = new class extends FakeFundingProviderAdapter
    {
        public function verifyFunding(FundingVerificationData $verification): ProviderFundingObservationData
        {
            throw new ProviderFundingNotObserved('No transfer observed.');
        }
    };
    replaceFundingAdapter($this->fundingAdapter);
    app()->call([new VerifyFundingWebhookReceiptJob($receipt->getKey()), 'handle']);

    expect($intent->refresh()->status)->toBe(FundingIntentStatus::AwaitingFunds)
        ->and($case->refresh()->status)->toBe('resolved')
        ->and($case->resolution_code)->toBe('verification_retry_no_funds_observed')
        ->and($case->resolution)->toBe([
            'funding_intent_status' => 'awaiting_funds',
        ]);
});

function verifiedFundingIntent(string $accountReference = 'wallet:account-1001'): FundingIntent
{
    $intent = app(CreateFundingIntent::class)->handle(new CreateFundingIntentData(
        accountReference: $accountReference,
        provider: 'netbank',
        expectedAmountMinor: 25_000,
        currency: 'PHP',
        idempotencyKey: 'verify-funding-'.str()->uuid(),
        actorType: 'operator',
        actorId: '42',
        expiresAt: new DateTimeImmutable('+30 minutes'),
    ));

    return app(IssueFundingInstructions::class)->handle($intent, 'operator', '42');
}

function authenticatedFundingReceipt(string $body = 'opaque-provider-body'): WebhookReceipt
{
    return app(StoreProviderWebhookReceipt::class)->handle(
        new ProviderWebhookRequestData(
            provider: 'netbank',
            rawBody: $body,
            contentType: 'text/plain;charset=ISO-8859-1',
            headers: [],
            sourceIp: '52.74.254.158',
            receivedAt: new DateTimeImmutable,
        ),
        new WebhookAuthenticationData(authenticated: true, method: 'test'),
        new ProviderEventHintData(eventType: 'netbank.credit-notification'),
    );
}

/**
 * @param  array<string, mixed>  $overrides
 */
function fundingObservation(array $overrides = []): ProviderFundingObservationData
{
    return new ProviderFundingObservationData(...array_merge([
        'provider' => 'netbank',
        'providerTransactionId' => 'provider-transaction-123',
        'grossAmountMinor' => 25_000,
        'feeAmountMinor' => 50,
        'netAmountMinor' => 24_950,
        'currency' => 'PHP',
        'providerStatus' => 'settled',
        'verificationSource' => 'fake-authoritative-api',
        'payloadHash' => hash('sha256', 'observation-'.str()->uuid()),
        'settledAt' => new DateTimeImmutable,
        'metadata' => ['destination_verified' => true],
    ], $overrides));
}

function replaceFundingAdapter(FakeFundingProviderAdapter $adapter): void
{
    app()->forgetInstance(FakeFundingProviderAdapter::class);
    app()->instance(FakeFundingProviderAdapter::class, $adapter);
    app()->forgetInstance(FundingProviderAdapterRegistry::class);
}
