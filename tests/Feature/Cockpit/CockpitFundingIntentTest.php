<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingIntentJob;
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
        ->assertSessionHas(
            'funding_instruction.qr_code',
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lDoLpwAAAABJRU5ErkJggg==',
        )
        ->assertSessionHas('funding_instruction.qr_mode', 'dynamic')
        ->assertSessionHas('funding_instruction.transaction_type', 'p2m')
        ->assertSessionHas('funding_instruction.embedded_amount', true)
        ->assertSessionHas('funding_instruction.provider_generated', true)
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
        ->assertJsonPath(
            'props.funding_instruction.qr_code',
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lDoLpwAAAABJRU5ErkJggg==',
        )
        ->assertJsonPath('props.funding_instruction.balance_changed', false)
        ->assertJsonMissingPath('props.funding_instruction.provider_request_id')
        ->assertJsonMissingPath('props.funding_instruction.provider_transaction_id');

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.funding.index'))
        ->assertOk()
        ->assertJsonPath('props.funding_instruction', null);
});

it('creates local simulator instructions without contacting a bank or changing the Account balance', function () {
    config([
        'x-change.funding.simulator.enabled' => true,
        'x-change.funding.providers.qrph_simulator.enabled' => true,
    ]);
    $operator = actingAsTestUser();
    $wallet = $operator->wallet;
    $balanceBefore = (int) $wallet->balance;
    $transactionCountBefore = $wallet->transactions()->count();

    $this->post(route('x-change.cockpit.funding.intents.store'), [
        'provider' => 'qrph_simulator',
        'amount_minor' => 2_500,
        'currency' => 'PHP',
        'idempotency_key' => 'cockpit-funding-simulator-1001',
    ])->assertRedirect(route('x-change.cockpit.funding.index'))
        ->assertSessionHas('funding_instruction.provider', 'qrph_simulator')
        ->assertSessionHas('funding_instruction.amount', '₱25.00')
        ->assertSessionHas('funding_instruction.status', 'awaiting_funds')
        ->assertSessionHas('funding_instruction.institution', 'QR Ph Simulator')
        ->assertSessionHas('funding_instruction.delivery', 'local-simulation-only')
        ->assertSessionHas('funding_instruction.balance_changed', false)
        ->assertSessionHas('funding_instruction.simulation_only', true)
        ->assertSessionHas('funding_instruction.sensitive', false);

    $intent = FundingIntent::query()->sole();

    expect($intent->provider_code)->toBe('qrph_simulator')
        ->and($intent->funding_address_ciphertext)->toStartWith('qrph-simulator:QRSIM-')
        ->and($intent->status->value)->toBe('awaiting_funds')
        ->and((int) $wallet->fresh()->balance)->toBe($balanceBefore)
        ->and($wallet->transactions()->count())->toBe($transactionCountBefore)
        ->and($this->fundingAdapter->instructionCalls)->toBe(0);
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

it('lets only the owner reopen an unexpired QR without caching it', function () {
    $operator = actingAsTestUser();

    $this->post(route('x-change.cockpit.funding.intents.store'), [
        'provider' => 'netbank',
        'amount_minor' => 25_000,
        'currency' => 'PHP',
        'idempotency_key' => 'cockpit-funding-reopen-1001',
    ])->assertRedirect();

    $intent = FundingIntent::query()->sole();
    $response = $this->getJson(route(
        'x-change.cockpit.funding.intents.instructions.show',
        $intent,
    ));

    $response
        ->assertOk()
        ->assertHeader('Pragma', 'no-cache')
        ->assertJsonPath('instruction.reference', $intent->reference)
        ->assertJsonPath('instruction.amount', '₱250.00')
        ->assertJsonPath('instruction.qr_mode', 'dynamic')
        ->assertJsonPath('instruction.embedded_amount', true)
        ->assertJsonPath(
            'instruction.qr_code',
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lDoLpwAAAABJRU5ErkJggg==',
        )
        ->assertJsonMissingPath('instruction.provider_request_id')
        ->assertJsonMissingPath('instruction.provider_transaction_id');

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->toContain('private');

    actingAsTestUser();

    $this->getJson(route(
        'x-change.cockpit.funding.intents.instructions.show',
        $intent,
    ))->assertForbidden();

    $this->actingAs($operator);
    $intent->forceFill(['expires_at' => now()->subMinute()])->saveQuietly();

    $this->getJson(route(
        'x-change.cockpit.funding.intents.instructions.show',
        $intent,
    ))->assertGone();
});

it('queues an owner-authorized NetBank check without accepting settlement facts', function () {
    $operator = actingAsTestUser();

    $this->post(route('x-change.cockpit.funding.intents.store'), [
        'provider' => 'netbank',
        'amount_minor' => 25_000,
        'currency' => 'PHP',
        'idempotency_key' => 'cockpit-funding-check-1001',
    ])->assertRedirect();

    $intent = FundingIntent::query()->sole();
    Queue::fake();

    $this->post(route(
        'x-change.cockpit.funding.intents.verification-checks.store',
        $intent,
    ), [
        'amount_minor' => 999_999_999,
        'account' => 'forged-account',
        'provider_transaction_id' => 'forged-transaction',
    ])->assertRedirect(route('x-change.cockpit.funding.index'))
        ->assertSessionHas(
            'funding_notice',
            'NetBank verification queued. Provider history will determine the result.',
        );

    Queue::assertPushed(
        VerifyFundingIntentJob::class,
        fn (VerifyFundingIntentJob $job): bool => $job->fundingIntentId === $intent->getKey()
            && $job->providerCode === 'netbank'
            && $job->actorId === (string) $operator->getAuthIdentifier()
            && $job->webhookReceiptId === null,
    );

    expect($intent->fresh()->expected_amount_minor)->toBe(25_000)
        ->and($intent->funding_address_ciphertext)->toBe('915001234567890123456');
});

it('rejects checks from other owners and ineligible intent states', function () {
    $operator = actingAsTestUser();

    $this->post(route('x-change.cockpit.funding.intents.store'), [
        'provider' => 'netbank',
        'amount_minor' => 25_000,
        'currency' => 'PHP',
        'idempotency_key' => 'cockpit-funding-check-guard-1001',
    ])->assertRedirect();

    $intent = FundingIntent::query()->sole();
    Queue::fake();
    actingAsTestUser();

    $this->postJson(route(
        'x-change.cockpit.funding.intents.verification-checks.store',
        $intent,
    ))->assertForbidden();

    $this->actingAs($operator);
    $intent->forceFill(['status' => FundingIntentStatus::Suspense])->saveQuietly();

    $this->postJson(route(
        'x-change.cockpit.funding.intents.verification-checks.store',
        $intent,
    ))->assertConflict();

    Queue::assertNotPushed(VerifyFundingIntentJob::class);
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
