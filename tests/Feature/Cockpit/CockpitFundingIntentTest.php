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

it('requires authentication to create Cockpit funding instructions', function () {
    $this->withHeader('Accept', 'application/json')
        ->post(route('x-change.cockpit.funding.intents.store'), [
            'provider' => 'netbank',
            'amount_minor' => 25_000,
            'currency' => 'PHP',
            'idempotency_key' => 'cockpit-funding-anonymous-1',
        ])
        ->assertUnauthorized();

    expect(FundingIntent::query()->count())->toBe(0);
});

it('creates exact one-time instructions without changing the Account balance', function () {
    $operator = actingAsTestUser();
    $wallet = $operator->wallet;
    $balanceBefore = (int) $wallet->balance;
    $transactionCountBefore = $wallet->transactions()->count();

    $response = $this->post(route('x-change.cockpit.funding.intents.store'), [
        'provider' => 'NETBANK',
        'amount_minor' => 25_000,
        'currency' => 'php',
        'idempotency_key' => 'cockpit-funding-1001',
    ]);

    $response
        ->assertRedirect(route('x-change.cockpit.funding.index'))
        ->assertSessionHas('funding_instruction.reference')
        ->assertSessionHas('funding_instruction.provider', 'netbank')
        ->assertSessionHas('funding_instruction.amount', '₱250.00')
        ->assertSessionHas('funding_instruction.status', 'awaiting_funds')
        ->assertSessionHas('funding_instruction.funding_address', '915001234567890123456')
        ->assertSessionHas('funding_instruction.balance_changed', false)
        ->assertSessionHas('funding_instruction.sensitive', true)
        ->assertSessionMissing('funding_instruction.provider_request_id')
        ->assertSessionMissing('funding_instruction.provider_transaction_id');

    $intent = FundingIntent::query()->sole();

    expect($intent->created_by_type)->toBe($operator::class)
        ->and($intent->created_by_id)->toBe((string) $operator->getAuthIdentifier())
        ->and($intent->status->value)->toBe('awaiting_funds')
        ->and((int) $wallet->fresh()->balance)->toBe($balanceBefore)
        ->and($wallet->transactions()->count())->toBe($transactionCountBefore)
        ->and($this->fundingAdapter->instructionCalls)->toBe(1);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.funding.index'))
        ->assertOk()
        ->assertJsonPath('props.funding_instruction.reference', $intent->reference)
        ->assertJsonPath('props.funding_instruction.funding_address', '915001234567890123456')
        ->assertJsonPath('props.funding_instruction.balance_changed', false)
        ->assertJsonMissingPath('props.funding_instruction.provider_request_id')
        ->assertJsonMissingPath('props.funding_instruction.provider_transaction_id');

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.funding.index'))
        ->assertOk()
        ->assertJsonPath('props.funding_instruction', null);
});

it('keeps browser retries idempotent and does not issue duplicate instructions', function () {
    actingAsTestUser();
    $payload = [
        'provider' => 'netbank',
        'amount_minor' => 25_000,
        'currency' => 'PHP',
        'idempotency_key' => 'cockpit-funding-retry-1001',
    ];

    $this->post(route('x-change.cockpit.funding.intents.store'), $payload)->assertRedirect();
    $this->post(route('x-change.cockpit.funding.intents.store'), $payload)->assertRedirect();

    expect(FundingIntent::query()->count())->toBe(1)
        ->and($this->fundingAdapter->instructionCalls)->toBe(1);
});

it('rejects invalid amounts and disabled providers before creating an intent', function () {
    actingAsTestUser();

    $this->from(route('x-change.cockpit.funding.index'))
        ->post(route('x-change.cockpit.funding.intents.store'), [
            'provider' => 'paynamics_constellation',
            'amount_minor' => 0,
            'currency' => 'PHP',
            'idempotency_key' => 'cockpit-funding-invalid-1001',
        ])
        ->assertRedirect(route('x-change.cockpit.funding.index'))
        ->assertSessionHasErrors(['provider', 'amount_minor']);

    expect(FundingIntent::query()->count())->toBe(0);
});
