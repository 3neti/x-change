<?php

declare(strict_types=1);

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

it('requires authentication to create a Funding Intent', function () {
    $this->postJson('/api/x/v1/funding-intents', [
        'provider' => 'netbank',
        'amount_minor' => 25_000,
        'currency' => 'PHP',
    ], [
        'Idempotency-Key' => 'anonymous-funding-1001',
    ])->assertUnauthorized();
});

it('creates a Funding Intent without changing the authenticated Account balance', function () {
    $user = actingAsTestUser();
    $wallet = $user->wallet;
    $balanceBefore = (int) $wallet->balance;
    $transactionCountBefore = $wallet->transactions()->count();

    $response = $this->postJson('/api/x/v1/funding-intents', [
        'provider' => 'NETBANK',
        'amount_minor' => 25_000,
        'currency' => 'php',
    ], [
        'Idempotency-Key' => 'authenticated-funding-1001',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.funding_intent.provider', 'netbank')
        ->assertJsonPath('data.funding_intent.expected_amount_minor', 25_000)
        ->assertJsonPath('data.funding_intent.currency', 'PHP')
        ->assertJsonPath('data.funding_intent.status', 'awaiting_funds')
        ->assertJsonPath('data.funding_intent.next_step', 'transfer_exact_amount_to_provider')
        ->assertJsonPath('data.funding_intent.funding_instructions.funding_address', '915001234567890123456')
        ->assertJsonPath('data.funding_intent.funding_instructions.amount_minor', 25_000)
        ->assertJsonPath('meta.balance_changed', false);

    expect(FundingIntent::query()->count())->toBe(1)
        ->and((int) $wallet->fresh()->balance)->toBe($balanceBefore)
        ->and($wallet->transactions()->count())->toBe($transactionCountBefore);
});

it('requires an idempotency key and minor-unit amount', function () {
    actingAsTestUser();

    $this->postJson('/api/x/v1/funding-intents', [
        'provider' => 'netbank',
        'amount' => 250.00,
        'currency' => 'PHP',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['amount_minor', 'idempotency_key']);
});

it('fails safely when the enabled provider adapter is unavailable', function () {
    actingAsTestUser();
    $this->app->instance(
        FundingProviderAdapterRegistry::class,
        new FundingProviderAdapterRegistry([]),
    );

    $this->postJson('/api/x/v1/funding-intents', [
        'provider' => 'netbank',
        'amount_minor' => 25_000,
        'currency' => 'PHP',
    ], [
        'Idempotency-Key' => 'unavailable-provider-1001',
    ])->assertServiceUnavailable()
        ->assertJsonPath('code', 'FUNDING_PROVIDER_UNAVAILABLE');

    expect(FundingIntent::query()->sole()->status->value)->toBe('pending_instructions');
});
