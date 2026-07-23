<?php

declare(strict_types=1);

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
use LBHurtado\XChange\Actions\Funding\CreateFundingIntent;
use LBHurtado\XChange\Actions\Funding\IssueFundingInstructions;
use LBHurtado\XChange\Actions\Funding\VerifyFundingWebhookReceipt;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Models\FundingIntent;
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
        ->and(ProviderFundingObservation::query()->count())->toBe(0);
});

it('moves settled amount or currency mismatches to suspense', function () {
    $this->fundingAdapter->fundingObservation = fundingObservation([
        'grossAmountMinor' => 24_000,
        'netAmountMinor' => 23_950,
    ]);
    $intent = verifiedFundingIntent();

    app(VerifyFundingWebhookReceipt::class)->handle(authenticatedFundingReceipt('mismatch'));

    expect($intent->fresh()->status)->toBe(FundingIntentStatus::Suspense)
        ->and(ProviderFundingObservation::query()->sole()->gross_amount_minor)->toBe(24_000);
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

it('marks authenticated evidence unmatched when no active intent exists', function () {
    $receipt = authenticatedFundingReceipt('unmatched');

    expect(app(VerifyFundingWebhookReceipt::class)->handle($receipt))->toBe(0)
        ->and($receipt->fresh()->processing_status)->toBe('unmatched')
        ->and(ProviderFundingObservation::query()->count())->toBe(0);
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
