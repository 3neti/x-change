<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Funding\ExpireOnDemandIssuanceFundingOrder;
use LBHurtado\XChange\Actions\Funding\ReserveOnDemandIssuanceAmountLease;
use LBHurtado\XChange\Actions\Funding\SettleVerifiedFundingIntent;
use LBHurtado\XChange\Actions\Funding\TransitionPayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Enums\FundingIntentPurpose;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\OnDemandIssuanceFundingBasis;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Jobs\Funding\ResumeOnDemandPayCodeIssuanceJob;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrderEvent;
use LBHurtado\XChange\Services\Cockpit\FundingMethodSelectorCockpitReadModel;
use LBHurtado\XChange\Services\Cockpit\OnDemandIssuanceFundingOrderPresenter;
use LBHurtado\XChange\Services\Funding\OnDemandIssuanceFundingPolicy;
use LBHurtado\XChange\Services\Funding\OnDemandIssuanceFundingRequirement;
use LBHurtado\XChange\Tests\Fakes\User;

it('is disabled by default and validates the configured application-wide basis', function (): void {
    $policy = app(OnDemandIssuanceFundingPolicy::class);

    expect($policy->enabled())->toBeFalse()
        ->and($policy->basis())->toBe(OnDemandIssuanceFundingBasis::FullAmount);

    config()->set('x-change.issuance_funding.on_demand.enabled', true);
    config()->set('x-change.issuance_funding.on_demand.basis', 'shortfall');

    expect($policy->enabled())->toBeTrue()
        ->and($policy->basis())->toBe(OnDemandIssuanceFundingBasis::Shortfall);
});

it('calculates full amount and shortfall funding from the authoritative balance', function (): void {
    $user = actingAsTestUser(3_000);
    $pricing = new PricingEstimateData(
        currency: 'PHP',
        pay_code_value: 50,
        account_debit: 50,
    );

    config()->set('x-change.issuance_funding.on_demand.basis', 'full_amount');
    $fullAmount = app(OnDemandIssuanceFundingRequirement::class)->for($user, $pricing);

    expect($fullAmount->basis)->toBe(OnDemandIssuanceFundingBasis::FullAmount)
        ->and($fullAmount->requiredAmountMinor)->toBe(5_000)
        ->and($fullAmount->reservedClientFundsMinor)->toBe(0)
        ->and($fullAmount->externalAmountMinor)->toBe(5_000);

    config()->set('x-change.issuance_funding.on_demand.basis', 'shortfall');
    $shortfall = app(OnDemandIssuanceFundingRequirement::class)->for($user, $pricing);

    expect($shortfall->basis)->toBe(OnDemandIssuanceFundingBasis::Shortfall)
        ->and($shortfall->requiredAmountMinor)->toBe(5_000)
        ->and($shortfall->reservedClientFundsMinor)->toBe(3_000)
        ->and($shortfall->externalAmountMinor)->toBe(2_000);
});

it('keeps bank transfer primary and fails fixed QR and Pay Code methods closed', function (): void {
    config()->set('x-change.funding.requests.bank_transfer.enabled', true);

    $selector = app(FundingMethodSelectorCockpitReadModel::class)->forOnDemandIssuance(
        orderReference: 'ORDER-1',
        amountMinor: 5_000,
        currency: 'PHP',
        status: 'awaiting_payment',
        expiresAt: now()->addMinutes(30)->toIso8601String(),
        fundingInstructions: [
            'reference' => 'INTENT-1',
            'funding_address' => '113-001-00001-9',
        ],
    );

    expect($selector['context'])->toBe('pay_code_issuance')
        ->and($selector['default_mode'])->toBe('bank_transfer')
        ->and(data_get($selector, 'amount.required_minor'))->toBe(5_000)
        ->and(data_get($selector, 'methods.0.key'))->toBe('bank_transfer')
        ->and(data_get($selector, 'methods.0.selectable'))->toBeTrue()
        ->and(data_get($selector, 'methods.1.key'))->toBe('qr_ph')
        ->and(data_get($selector, 'methods.1.selectable'))->toBeFalse()
        ->and(data_get($selector, 'methods.2.key'))->toBe('pay_code')
        ->and(data_get($selector, 'methods.2.selectable'))->toBeFalse();
});

it('projects an exact fixed-amount QR Ph without changing the default payment method', function (): void {
    config()->set('x-change.funding.requests.bank_transfer.enabled', true);
    $selector = app(FundingMethodSelectorCockpitReadModel::class)->forOnDemandIssuance(
        orderReference: 'ORDER-QR-1',
        amountMinor: 5_317,
        currency: 'PHP',
        status: 'awaiting_payment',
        expiresAt: now()->addMinutes(30)->toIso8601String(),
        fundingInstructions: [
            'reference' => 'INTENT-QR-1',
            'qr_code' => 'data:image/png;base64,ZmFrZQ==',
            'qr_mode' => 'dynamic',
            'transaction_type' => 'p2m',
            'embedded_amount' => true,
            'provider_generated' => true,
        ],
    );

    expect($selector['default_mode'])->toBe('bank_transfer')
        ->and(data_get($selector, 'methods.1.selectable'))->toBeTrue()
        ->and(data_get($selector, 'qr_ph.fixed_amount'))->toBeTrue()
        ->and(data_get($selector, 'qr_ph.amount_minor'))->toBe(5_317)
        ->and(data_get($selector, 'qr_ph.image'))->toBe('data:image/png;base64,ZmFrZQ==')
        ->and(data_get($selector, 'qr_ph.provider_generated'))->toBeTrue();
});

it('persists append-only order state and exposes only owner-scoped routes', function (): void {
    $user = User::query()->create([
        'name' => 'On-Demand Operator',
        'email' => 'on-demand@example.test',
        'password' => 'password',
    ]);
    $order = issuanceFundingOrder($user);

    $transitioned = app(TransitionPayCodeIssuanceFundingOrder::class)->handle(
        order: $order,
        status: PayCodeIssuanceFundingOrderStatus::PayerAcknowledged,
        eventType: 'payer_acknowledged',
        actorType: $user::class,
        actorId: (string) $user->getKey(),
        attributes: ['payer_acknowledged_at' => now()],
    );

    expect($transitioned->status)->toBe(PayCodeIssuanceFundingOrderStatus::PayerAcknowledged)
        ->and($transitioned->version)->toBe(2)
        ->and(PayCodeIssuanceFundingOrderEvent::query()->count())->toBe(2)
        ->and(fn () => $transitioned->update(['required_amount_minor' => 1]))
        ->toThrow(LogicException::class, 'guarded actions')
        ->and(Route::has('x-change.cockpit.quick-generate.funding-orders.show'))->toBeTrue()
        ->and(Route::has('x-change.cockpit.quick-generate.funding-orders.acknowledge'))->toBeTrue()
        ->and(Route::has('x-change.cockpit.quick-generate.funding-orders.cancel'))->toBeTrue();
});

it('isolates polling, acknowledgement, and cancellation throttle buckets', function (): void {
    $showMiddleware = Route::getRoutes()
        ->getByName('x-change.cockpit.quick-generate.funding-orders.show')
        ?->gatherMiddleware();
    $acknowledgeMiddleware = Route::getRoutes()
        ->getByName('x-change.cockpit.quick-generate.funding-orders.acknowledge')
        ?->gatherMiddleware();
    $cancelMiddleware = Route::getRoutes()
        ->getByName('x-change.cockpit.quick-generate.funding-orders.cancel')
        ?->gatherMiddleware();

    expect($showMiddleware)->toContain('throttle:60,1,quick-generate-funding-order-read:')
        ->and($acknowledgeMiddleware)->toContain('throttle:6,1,quick-generate-funding-order-check:')
        ->and($cancelMiddleware)->toContain('throttle:6,1,quick-generate-funding-order-cancel:');
});

it('keeps write capacity available after repeated funding-order polling', function (): void {
    Queue::fake();
    $user = actingAsTestUser(0);
    $order = issuanceFundingOrder($user, 'polling-throttle-isolation');
    $intent = FundingIntent::query()->create([
        'account_reference' => $order->account_reference,
        'provider_code' => 'netbank',
        'purpose' => FundingIntentPurpose::OnDemandIssuance,
        'expected_amount_minor' => 5_000,
        'currency' => 'PHP',
        'status' => FundingIntentStatus::AwaitingFunds,
        'version' => 1,
        'idempotency_key_hash' => hash('sha256', 'polling-intent-key'),
        'idempotency_fingerprint' => hash('sha256', 'polling-intent-fingerprint'),
        'created_by_type' => $user::class,
        'created_by_id' => (string) $user->getAuthIdentifier(),
        'expires_at' => now()->addMinutes(30),
    ]);
    $order->forceFill(['funding_intent_id' => $intent->getKey()])->saveQuietly();

    foreach (range(1, 7) as $poll) {
        $this->getJson(route(
            'x-change.cockpit.quick-generate.funding-orders.show',
            $order,
        ))->assertSuccessful();
    }

    $this->postJson(route(
        'x-change.cockpit.quick-generate.funding-orders.acknowledge',
        $order,
    ))->assertAccepted()
        ->assertHeader('X-RateLimit-Limit', '6');

    expect($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::PayerAcknowledged);
});

it('moves failed instruction preparation to operator attention and permits cancellation', function (): void {
    $user = User::query()->create([
        'name' => 'On-Demand Operator',
        'email' => 'on-demand-attention@example.test',
        'password' => 'password',
    ]);
    $order = issuanceFundingOrder($user);

    $attention = app(TransitionPayCodeIssuanceFundingOrder::class)->handle(
        order: $order,
        status: PayCodeIssuanceFundingOrderStatus::IssuanceAttention,
        eventType: 'funding_instructions_failed',
        actorType: 'system',
        actorId: $order->reference,
        metadata: ['failure_type' => 'ProviderUnavailable'],
    );

    expect($attention->status)->toBe(PayCodeIssuanceFundingOrderStatus::IssuanceAttention)
        ->and($attention->events->last()->event_type)->toBe('funding_instructions_failed')
        ->and($attention->events->last()->metadata)->toBe([
            'failure_type' => 'ProviderUnavailable',
        ]);

    $cancelled = app(TransitionPayCodeIssuanceFundingOrder::class)->handle(
        order: $attention,
        status: PayCodeIssuanceFundingOrderStatus::Cancelled,
        eventType: 'cancelled',
        actorType: $user::class,
        actorId: (string) $user->getKey(),
    );

    expect($cancelled->status)->toBe(PayCodeIssuanceFundingOrderStatus::Cancelled);
});

it('leases distinct identifying amounts for concurrent orders in the same provider scope', function (): void {
    $user = actingAsTestUser(0);
    $first = issuanceFundingOrder($user, 'lease-first');
    $second = issuanceFundingOrder($user, 'lease-second');
    $leases = app(ReserveOnDemandIssuanceAmountLease::class);

    $first = $leases->handle($first);
    $second = $leases->handle($second);

    expect($first->expected_payment_minor)->toBe(5_000)
        ->and($first->reconciliation_adjustment_minor)->toBe(0)
        ->and($second->expected_payment_minor)->toBe(5_001)
        ->and($second->reconciliation_adjustment_minor)->toBe(1)
        ->and($first->amount_lease_active_key)->not->toBe($second->amount_lease_active_key)
        ->and($first->events->last()->event_type)->toBe('transfer_amount_leased')
        ->and($second->events->last()->event_type)->toBe('transfer_amount_leased');
});

it('enforces active amount ownership with a database uniqueness boundary', function (): void {
    $user = actingAsTestUser(0);
    $first = app(ReserveOnDemandIssuanceAmountLease::class)->handle(
        issuanceFundingOrder($user, 'database-lease-owner'),
    );
    $second = issuanceFundingOrder($user, 'database-lease-collision');

    expect(function () use ($first, $second): void {
        $second->forceFill([
            'amount_lease_active_key' => $first->amount_lease_active_key,
        ])->saveQuietly();
    })->toThrow(QueryException::class);
});

it('keeps a cancelled amount leased until its cooling period ends and then permits reuse', function (): void {
    config()->set('x-change.issuance_funding.on_demand.amount_lease.reuse_delay_seconds', 60);
    $user = actingAsTestUser(0);
    $leases = app(ReserveOnDemandIssuanceAmountLease::class);
    $first = $leases->handle(issuanceFundingOrder($user, 'lease-cooling'));

    app(TransitionPayCodeIssuanceFundingOrder::class)->handle(
        order: $first,
        status: PayCodeIssuanceFundingOrderStatus::Cancelled,
        eventType: 'cancelled',
        actorType: 'test',
        actorId: 'operator',
        attributes: ['cancelled_at' => now()],
    );

    $duringCooling = $leases->handle(issuanceFundingOrder($user, 'lease-during-cooling'));
    expect($duringCooling->expected_payment_minor)->toBe(5_001);

    $this->travelTo($first->expires_at->addSeconds(61));
    $this->artisan('xchange:funding:expire-issuance-orders')->assertSuccessful();
    $afterCooling = $leases->handle(issuanceFundingOrder($user, 'lease-after-cooling'));

    expect($first->refresh()->amount_lease_active_key)->toBeNull()
        ->and($first->amount_lease_released_at)->not->toBeNull()
        ->and($afterCooling->expected_payment_minor)->toBe(5_000);
});

it('expires an unpaid order without releasing its identifying amount early', function (): void {
    $user = actingAsTestUser(0);
    $order = app(ReserveOnDemandIssuanceAmountLease::class)->handle(
        issuanceFundingOrder($user, 'expiry'),
    );
    $order->forceFill(['expires_at' => now()->subSecond()])->saveQuietly();

    $expired = app(ExpireOnDemandIssuanceFundingOrder::class)->handle($order);

    expect($expired->status)->toBe(PayCodeIssuanceFundingOrderStatus::Expired)
        ->and($expired->expired_at)->not->toBeNull()
        ->and($expired->amount_lease_active_key)->not->toBeNull()
        ->and($expired->events->last()->event_type)->toBe('funding_order_expired');
});

it('credits a late verified payment to Client Funds without reviving issuance', function (): void {
    Queue::fake();
    enableNetbankTreasuryForTests();
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->sole();
    $observation = onDemandFundingObservation(5_000);
    $intent = onDemandFundingIntent($wallet->uuid, $observation, 5_000);
    $order = app(ReserveOnDemandIssuanceAmountLease::class)->handle(
        issuanceFundingOrder($user, 'late-payment'),
    );
    $order->forceFill([
        'funding_intent_id' => $intent->getKey(),
        'status' => PayCodeIssuanceFundingOrderStatus::Expired,
        'expired_at' => now(),
    ])->saveQuietly();

    app(SettleVerifiedFundingIntent::class)->handle($intent);

    $order->refresh();
    expect($order->status)->toBe(PayCodeIssuanceFundingOrderStatus::Expired)
        ->and($order->late_payment_disposition)->toBe('client_funds')
        ->and($order->late_payment_detected_at)->not->toBeNull()
        ->and($order->amount_lease_active_key)->toBeNull()
        ->and($order->amount_lease_released_at)->not->toBeNull()
        ->and($order->events()->where(
            'event_type',
            'late_payment_credited_to_client_funds',
        )->exists())->toBeTrue()
        ->and(treasuryClientFundsLedger($user)->getBalanceIntAttribute())->toBe(5_000);

    Queue::assertNotPushed(ResumeOnDemandPayCodeIssuanceJob::class);
});

it('restores the signed-in owner active funding order on quick generate reload', function (): void {
    $owner = actingAsTestUser(0);
    $order = issuanceFundingOrder($owner);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()
        ->assertJsonPath(
            'props.active_on_demand_funding_order.schema',
            'x-change.cockpit.on-demand-issuance-funding.v1',
        )
        ->assertJsonPath('props.active_on_demand_funding_order.order.reference', $order->reference)
        ->assertJsonPath('props.active_on_demand_funding_order.order.status', 'awaiting_payment');
});

it('settles exact provider funds into a hold and resumes issuance exactly once', function (): void {
    Queue::fake();
    enableNetbankTreasuryForTests();
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->sole();
    $observation = onDemandFundingObservation(5_000);
    $intent = onDemandFundingIntent($wallet->uuid, $observation, 5_000);
    $order = app(ReserveOnDemandIssuanceAmountLease::class)->handle(
        issuanceFundingOrder($user),
    );
    $order->forceFill(['funding_intent_id' => $intent->getKey()])->saveQuietly();

    app(SettleVerifiedFundingIntent::class)->handle($intent);

    expect($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::Funded)
        ->and($order->treasury_hold_reference)->not->toBeNull()
        ->and($order->amount_lease_active_key)->toBeNull()
        ->and($order->amount_lease_released_at)->not->toBeNull()
        ->and(treasuryClientFundsLedger($user)->getBalanceIntAttribute())->toBe(0);

    Queue::assertPushed(
        ResumeOnDemandPayCodeIssuanceJob::class,
        fn (ResumeOnDemandPayCodeIssuanceJob $job): bool => $job->fundingOrderId === $order->getKey(),
    );

    $voucher = Voucher::query()->create([
        'code' => 'ODIF-4242',
        'metadata' => ['source' => 'on_demand_issuance_test'],
    ]);
    $result = new GeneratePayCodeResultData(
        voucher_id: $voucher->getKey(),
        code: 'ODIF-4242',
        amount: 50,
        currency: 'PHP',
        issuer: new IssuerData(id: $user->getKey()),
        cost: new PricingEstimateData(currency: 'PHP', pay_code_value: 50, account_debit: 50),
        wallet: ['balance_before' => 50, 'balance_after' => 0],
        debit: new DebitData(id: 99, amount: 50),
        links: new PayCodeLinksData(
            redeem: 'https://example.test/x/claim/ODIF-4242',
            redeem_path: '/x/claim/ODIF-4242',
        ),
    );
    $generate = Mockery::mock(GeneratePayCode::class);
    $generate->shouldReceive('handle')
        ->once()
        ->with(Mockery::on(
            fn (array $instructions): bool => data_get($instructions, '_meta.on_demand_funding_order') === $order->reference
                && data_get($instructions, 'metadata.issuer_id') === (string) $user->getKey(),
        ))
        ->andReturn($result);
    app()->instance(GeneratePayCode::class, $generate);

    $job = new ResumeOnDemandPayCodeIssuanceJob($order->getKey());
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    expect($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::Issued)
        ->and($order->voucher_id)->toBe($voucher->getKey())
        ->and($order->voucher->is($voucher))->toBeTrue()
        ->and($order->events()->pluck('event_type')->all())->toBe([
            'prepared',
            'transfer_amount_leased',
            'funding_verified_and_held',
            'issuance_started',
            'pay_code_issued',
        ]);

    $projection = app(OnDemandIssuanceFundingOrderPresenter::class)->present($order->refresh());
    expect(data_get($projection, 'lifecycle.current'))->toBe('pay_code_ready')
        ->and(data_get($projection, 'order.voucher.code'))->toBe('ODIF-4242')
        ->and(data_get($projection, 'order.voucher.claim_qr'))->toStartWith('data:image/png;base64,')
        ->and(data_get($projection, 'order.voucher.share_card_url'))->toBeString()
        ->and(data_get($projection, 'order.receipt.order_reference'))->toBe($order->reference)
        ->and(data_get($projection, 'order.receipt.issued_at'))->not->toBeNull();
});

function issuanceFundingOrder(User $user, ?string $identity = null): PayCodeIssuanceFundingOrder
{
    $identity ??= (string) Str::uuid();
    $order = PayCodeIssuanceFundingOrder::query()->create([
        'account_reference' => 'wallet:test',
        'issuer_type' => $user::class,
        'issuer_id' => (string) $user->getKey(),
        'provider' => 'netbank',
        'connection_reference' => 'netbank-primary',
        'funding_basis' => OnDemandIssuanceFundingBasis::FullAmount,
        'instructions_ciphertext' => ['cash' => ['amount' => 50, 'currency' => 'PHP']],
        'instructions_fingerprint' => str_repeat('a', 64),
        'pricing_snapshot_ciphertext' => ['account_debit' => 50],
        'pricing_fingerprint' => str_repeat('b', 64),
        'required_amount_minor' => 5_000,
        'reserved_client_funds_minor' => 0,
        'on_demand_amount_minor' => 5_000,
        'reconciliation_adjustment_minor' => 0,
        'expected_payment_minor' => 5_000,
        'currency' => 'PHP',
        'status' => PayCodeIssuanceFundingOrderStatus::AwaitingPayment,
        'version' => 1,
        'idempotency_key_hash' => hash('sha256', 'key-'.$identity),
        'idempotency_fingerprint' => hash('sha256', 'fingerprint-'.$identity),
        'expires_at' => now()->addMinutes(30),
    ]);
    $order->events()->create([
        'sequence' => 1,
        'event_type' => 'prepared',
        'from_status' => null,
        'to_status' => PayCodeIssuanceFundingOrderStatus::AwaitingPayment,
        'actor_type' => $user::class,
        'actor_id' => (string) $user->getKey(),
        'occurred_at' => now(),
    ]);

    return $order;
}

function onDemandFundingObservation(int $amountMinor): ProviderFundingObservation
{
    $transactionId = 'NB-'.Str::upper(Str::random(12));

    return ProviderFundingObservation::query()->create([
        'observation_key' => hash('sha256', $transactionId),
        'provider_code' => 'netbank',
        'provider_transaction_id' => $transactionId,
        'provider_operation_id' => 'OP-'.$transactionId,
        'request_id' => 'REQ-'.$transactionId,
        'funding_address' => '001234567890',
        'provider_account_reference' => 'corporate-vca',
        'gross_amount_minor' => $amountMinor,
        'fee_amount_minor' => 0,
        'net_amount_minor' => $amountMinor,
        'currency' => 'PHP',
        'provider_status' => 'settled',
        'occurred_at' => now()->subMinute(),
        'settled_at' => now(),
        'verification_source' => 'transaction_history',
        'payload_hash' => hash('sha256', 'payload-'.$transactionId),
        'metadata' => ['destination_verified' => true],
    ]);
}

function onDemandFundingIntent(
    string $walletUuid,
    ProviderFundingObservation $observation,
    int $amountMinor,
): FundingIntent {
    $idempotency = (string) Str::uuid();

    return FundingIntent::query()->create([
        'account_reference' => 'wallet:'.$walletUuid,
        'provider_code' => 'netbank',
        'purpose' => FundingIntentPurpose::OnDemandIssuance,
        'expected_amount_minor' => $amountMinor,
        'currency' => 'PHP',
        'status' => FundingIntentStatus::Verified,
        'version' => 4,
        'idempotency_key_hash' => hash('sha256', $idempotency),
        'idempotency_fingerprint' => hash('sha256', 'fingerprint-'.$idempotency),
        'created_by_type' => 'test',
        'created_by_id' => 'operator-1',
        'provider_reference' => 'VCA-'.$walletUuid,
        'provider_request_id' => $observation->request_id,
        'funding_address_ciphertext' => $observation->funding_address,
        'funding_address_hash' => hash('sha256', (string) $observation->funding_address),
        'matched_observation_id' => $observation->getKey(),
        'provider_transaction_id' => $observation->provider_transaction_id,
        'instructions_created_at' => now()->subMinutes(2),
        'evidence_received_at' => now()->subMinute(),
        'verified_at' => now(),
        'expires_at' => now()->addMinutes(30),
        'metadata' => ['source' => 'on_demand_issuance_test'],
    ]);
}
