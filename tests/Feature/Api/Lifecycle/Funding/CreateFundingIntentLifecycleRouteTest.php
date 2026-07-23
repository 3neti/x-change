<?php

declare(strict_types=1);

use LBHurtado\XChange\Models\FundingIntent;

beforeEach(function () {
    config()->set('x-change.funding.providers.netbank.enabled', true);
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
        ->assertJsonPath('data.funding_intent.status', 'pending_instructions')
        ->assertJsonPath('data.funding_intent.next_step', 'create_provider_instructions')
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
