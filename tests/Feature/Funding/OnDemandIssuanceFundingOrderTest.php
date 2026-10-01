<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Exceptions\ProviderFundingNotObserved;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Funding\ClaimFundingEvidence;
use LBHurtado\XChange\Actions\Funding\ClassifyOnDemandIssuanceFundingMismatch;
use LBHurtado\XChange\Actions\Funding\ExpireOnDemandIssuanceFundingOrder;
use LBHurtado\XChange\Actions\Funding\ReserveOnDemandIssuanceAmountLease;
use LBHurtado\XChange\Actions\Funding\ReverseSettledFundingIntent;
use LBHurtado\XChange\Actions\Funding\SettleVerifiedFundingIntent;
use LBHurtado\XChange\Actions\Funding\TransitionPayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Actions\Funding\VerifyFundingIntent;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\Funding\FundingIntentVerificationData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Enums\FundingIntentPurpose;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\FundingVerificationTrigger;
use LBHurtado\XChange\Enums\OnDemandIssuanceFundingBasis;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Exceptions\FundingEvidenceAlreadyClaimed;
use LBHurtado\XChange\Jobs\Funding\ResumeOnDemandPayCodeIssuanceJob;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingIntentJob;
use LBHurtado\XChange\Models\FundingEvidenceClaim;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrderEvent;
use LBHurtado\XChange\Services\Cockpit\FundingInstructionPresenter;
use LBHurtado\XChange\Services\Cockpit\FundingMethodSelectorCockpitReadModel;
use LBHurtado\XChange\Services\Cockpit\OnDemandIssuanceFundingOrderPresenter;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Services\Funding\OnDemandIssuanceFundingPolicy;
use LBHurtado\XChange\Services\Funding\OnDemandIssuanceFundingRequirement;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;
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

it('presents the corporate destination for on-demand bank transfer without replacing the QR address', function (): void {
    $intent = fundingIntentAwaitingOnDemandBankTransfer();
    $qrAddress = '915008422914050308952';
    $qrPayload = base64_encode("\x89PNG\r\n\x1a\nfixture");

    $intent->forceFill([
        'instructions_ciphertext' => [
            'provider' => 'netbank',
            'amount_minor' => 6_650,
            'currency' => 'PHP',
            'funding_address' => $qrAddress,
            'display_data' => [
                'institution' => 'NetBank',
                'account_name' => 'QR Merchant',
                'destination_account' => $qrAddress,
                'delivery' => 'scan-to-pay',
            ],
            'qr_code' => [
                'mime_type' => 'image/png',
                'base64_payload' => $qrPayload,
                'qr_mode' => 'dynamic',
                'transaction_type' => 'p2m',
                'embedded_amount' => true,
                'provider_generated' => true,
            ],
        ],
    ])->saveQuietly();

    $projection = app(FundingInstructionPresenter::class)->forIntent($intent->refresh());

    expect($projection['funding_address'])->toBe('113-001-00001-9')
        ->and($projection['account_name'])->toBe('Test Treasury')
        ->and($projection['qr_code'])->toBe('data:image/png;base64,'.$qrPayload)
        ->and($intent->refresh()->funding_address_ciphertext)->toBe($qrAddress);
});

it('preserves provider funding addresses outside on-demand issuance', function (): void {
    $intent = fundingIntentAwaitingOnDemandBankTransfer();
    $intent->forceFill([
        'purpose' => FundingIntentPurpose::AccountFunding,
        'instructions_ciphertext' => [
            'provider' => 'netbank',
            'amount_minor' => 6_650,
            'currency' => 'PHP',
            'funding_address' => '915008422914050308952',
            'display_data' => [
                'institution' => 'NetBank',
                'account_name' => 'QR Merchant',
            ],
        ],
    ])->saveQuietly();

    $projection = app(FundingInstructionPresenter::class)->forIntent($intent->refresh());

    expect($projection['funding_address'])->toBe('915008422914050308952')
        ->and($projection['account_name'])->toBe('QR Merchant');
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
        ->and(Route::has('x-change.cockpit.quick-generate.funding-orders.verification'))->toBeTrue()
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
    $verificationMiddleware = Route::getRoutes()
        ->getByName('x-change.cockpit.quick-generate.funding-orders.verification')
        ?->gatherMiddleware();

    expect($showMiddleware)->toContain('throttle:60,1,quick-generate-funding-order-read:')
        ->and($acknowledgeMiddleware)->toContain('throttle:6,1,quick-generate-funding-order-check:')
        ->and($verificationMiddleware)->toContain('throttle:15,1,quick-generate-funding-order-monitor:')
        ->and($cancelMiddleware)->toContain('throttle:6,1,quick-generate-funding-order-cancel:');
});

it('queues one automatic provider check without claiming that the payer acknowledged payment', function (): void {
    Cache::clear();
    Queue::fake();
    config()->set('x-change.issuance_funding.on_demand.automatic_verification.enabled', true);
    config()->set('x-change.issuance_funding.on_demand.automatic_verification.interval_seconds', 10);
    $user = actingAsTestUser(0);
    $order = issuanceFundingOrder($user, 'automatic-modal-verification');
    $intent = FundingIntent::query()->create([
        'account_reference' => $order->account_reference,
        'provider_code' => 'netbank',
        'purpose' => FundingIntentPurpose::OnDemandIssuance,
        'expected_amount_minor' => 5_000,
        'currency' => 'PHP',
        'status' => FundingIntentStatus::AwaitingFunds,
        'version' => 1,
        'idempotency_key_hash' => hash('sha256', 'automatic-modal-intent-key'),
        'idempotency_fingerprint' => hash('sha256', 'automatic-modal-intent-fingerprint'),
        'created_by_type' => $user::class,
        'created_by_id' => (string) $user->getAuthIdentifier(),
        'expires_at' => now()->addMinutes(30),
    ]);
    $order->forceFill(['funding_intent_id' => $intent->getKey()])->saveQuietly();

    $route = route(
        'x-change.cockpit.quick-generate.funding-orders.verification',
        $order,
    );
    $this->postJson($route)->assertAccepted()
        ->assertJsonPath('monitor.eligible', true);
    $this->postJson($route)->assertAccepted();

    expect($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::AwaitingPayment)
        ->and($order->payer_acknowledged_at)->toBeNull();
    Queue::assertPushed(
        VerifyFundingIntentJob::class,
        1,
    );
    Queue::assertPushed(
        VerifyFundingIntentJob::class,
        fn (VerifyFundingIntentJob $job): bool => $job->fundingIntentId === $intent->getKey()
            && $job->trigger === FundingVerificationTrigger::Schedule
            && $job->actorId === 'funding-modal-monitor',
    );
});

it('returns an expired order outcome without queuing an ineligible payment check', function (): void {
    Cache::clear();
    Queue::fake();
    $user = actingAsTestUser(0);
    $order = issuanceFundingOrder($user, 'expired-modal-verification');
    $intent = FundingIntent::query()->create([
        'account_reference' => $order->account_reference,
        'provider_code' => 'netbank',
        'purpose' => FundingIntentPurpose::OnDemandIssuance,
        'expected_amount_minor' => 5_000,
        'currency' => 'PHP',
        'status' => FundingIntentStatus::Settled,
        'version' => 1,
        'idempotency_key_hash' => hash('sha256', 'expired-modal-intent-key'),
        'idempotency_fingerprint' => hash('sha256', 'expired-modal-intent-fingerprint'),
        'created_by_type' => $user::class,
        'created_by_id' => (string) $user->getAuthIdentifier(),
        'expires_at' => now()->subMinute(),
    ]);
    $order->forceFill([
        'funding_intent_id' => $intent->getKey(),
        'status' => PayCodeIssuanceFundingOrderStatus::Expired,
        'expires_at' => now()->subMinute(),
        'late_payment_detected_at' => now(),
        'late_payment_disposition' => 'client_funds',
    ])->saveQuietly();

    $this->postJson(route(
        'x-change.cockpit.quick-generate.funding-orders.verification',
        $order,
    ))->assertSuccessful()
        ->assertJsonPath('monitor.eligible', false)
        ->assertJsonPath('order.late_payment_disposition', 'client_funds')
        ->assertJsonPath(
            'lifecycle.message',
            'Your payment arrived after this order expired and was added to Client Funds. No Pay Code was issued.',
        );

    Queue::assertNothingPushed();
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

it('classifies an authoritative underpayment without issuing or offering cancellation', function (): void {
    $user = actingAsTestUser(0);
    $order = issuanceFundingOrder($user, 'underpayment-classification');
    $observation = onDemandFundingObservation(4_999);
    $intent = onDemandFundingIntent(
        $user->wallet()->where('slug', 'platform')->sole()->uuid,
        $observation,
        5_000,
    );
    $intent->forceFill(['status' => FundingIntentStatus::Verifying])->saveQuietly();
    $order->forceFill(['funding_intent_id' => $intent->getKey()])->saveQuietly();

    $classification = app(ClassifyOnDemandIssuanceFundingMismatch::class)->handle(
        $intent,
        $observation,
    );
    $projection = app(OnDemandIssuanceFundingOrderPresenter::class)->present($order->refresh());

    expect($classification->reasonCode)->toBe('on_demand_issuance_underpayment')
        ->and($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::Underfunded)
        ->and($order->voucher_id)->toBeNull()
        ->and(data_get($projection, 'order.can_cancel'))->toBeFalse()
        ->and(data_get($projection, 'monitor.eligible'))->toBeFalse()
        ->and(data_get($projection, 'lifecycle.message'))
        ->toContain('below the exact amount');
});

it('classifies an authoritative excess payment for review without issuing', function (): void {
    $user = actingAsTestUser(0);
    $order = issuanceFundingOrder($user, 'excess-payment-classification');
    $observation = onDemandFundingObservation(5_001);
    $intent = onDemandFundingIntent(
        $user->wallet()->where('slug', 'platform')->sole()->uuid,
        $observation,
        5_000,
    );
    $intent->forceFill(['status' => FundingIntentStatus::Verifying])->saveQuietly();
    $order->forceFill(['funding_intent_id' => $intent->getKey()])->saveQuietly();

    $classification = app(ClassifyOnDemandIssuanceFundingMismatch::class)->handle(
        $intent,
        $observation,
    );

    expect($classification->reasonCode)->toBe('on_demand_issuance_excess_payment')
        ->and($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::PaymentAmbiguous)
        ->and($order->voucher_id)->toBeNull()
        ->and($order->events()->where('event_type', 'payment_excess_requires_review')->exists())
        ->toBeTrue();
});

it('claims provider evidence once and permits an idempotent same-intent replay', function (): void {
    $user = actingAsTestUser(0);
    $observation = onDemandFundingObservation(5_000);
    $firstIntent = onDemandFundingIntent(
        $user->wallet()->where('slug', 'platform')->sole()->uuid,
        $observation,
        5_000,
    );
    $secondIntent = onDemandFundingIntent(
        $user->wallet()->where('slug', 'platform')->sole()->uuid,
        $observation,
        5_000,
    );
    $claims = app(ClaimFundingEvidence::class);

    $first = $claims->handle($firstIntent, $observation);
    $replayed = $claims->handle($firstIntent, $observation);

    expect($replayed->is($first))->toBeTrue()
        ->and(FundingEvidenceClaim::query()->count())->toBe(1)
        ->and(fn () => $claims->handle($secondIntent, $observation))
        ->toThrow(FundingEvidenceAlreadyClaimed::class);
});

it('refuses queued issuance after a provider reversal marker is recorded', function (): void {
    $user = actingAsTestUser(0);
    $order = issuanceFundingOrder($user, 'provider-reversal-race');
    $order->forceFill([
        'status' => PayCodeIssuanceFundingOrderStatus::IssuanceAttention,
        'treasury_hold_reference' => 'issuance-hold:reversed',
        'metadata' => [
            'provider_reversal' => [
                'observation_id' => 999,
                'status' => 'reversed',
            ],
        ],
    ])->saveQuietly();

    $job = new ResumeOnDemandPayCodeIssuanceJob($order->getKey());

    expect(fn () => app()->call([$job, 'handle']))
        ->toThrow(RuntimeException::class, 'blocked by a provider reversal')
        ->and($order->refresh()->status)
        ->toBe(PayCodeIssuanceFundingOrderStatus::IssuanceAttention)
        ->and($order->voucher_id)->toBeNull();
});

it('records an authoritative provider reversal before issuance and blocks the queued job', function (): void {
    Queue::fake();
    enableNetbankTreasuryForTests();
    $user = actingAsTestUser(0);
    $observation = onDemandFundingObservation(5_000);
    $intent = onDemandFundingIntent(
        $user->wallet()->where('slug', 'platform')->sole()->uuid,
        $observation,
        5_000,
    );
    $order = app(ReserveOnDemandIssuanceAmountLease::class)->handle(
        issuanceFundingOrder($user, 'authoritative-provider-reversal'),
    );
    $order->forceFill(['funding_intent_id' => $intent->getKey()])->saveQuietly();
    app(SettleVerifiedFundingIntent::class)->handle($intent);
    $reversal = onDemandFundingReversalObservation($observation);

    app(ReverseSettledFundingIntent::class)->handle($intent->refresh(), $reversal);
    $projection = app(OnDemandIssuanceFundingOrderPresenter::class)->present($order->refresh());

    expect($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::IssuanceAttention)
        ->and(data_get($order->metadata, 'provider_reversal.observation_id'))
        ->toBe($reversal->getKey())
        ->and($order->events()->where(
            'event_type',
            'provider_reversal_blocked_issuance',
        )->exists())->toBeTrue()
        ->and(data_get($projection, 'order.can_cancel'))->toBeFalse()
        ->and($order->voucher_id)->toBeNull()
        ->and(fn () => app()->call([
            new ResumeOnDemandPayCodeIssuanceJob($order->getKey()),
            'handle',
        ]))->toThrow(RuntimeException::class, 'blocked by a provider reversal');
});

it('preserves an issued Pay Code while recording a later provider reversal', function (): void {
    Queue::fake();
    enableNetbankTreasuryForTests();
    $user = actingAsTestUser(0);
    $observation = onDemandFundingObservation(5_000);
    $intent = onDemandFundingIntent(
        $user->wallet()->where('slug', 'platform')->sole()->uuid,
        $observation,
        5_000,
    );
    $order = app(ReserveOnDemandIssuanceAmountLease::class)->handle(
        issuanceFundingOrder($user, 'post-issuance-provider-reversal'),
    );
    $order->forceFill(['funding_intent_id' => $intent->getKey()])->saveQuietly();
    app(SettleVerifiedFundingIntent::class)->handle($intent);
    $voucher = Voucher::query()->create([
        'code' => 'ODIF-REV1',
        'metadata' => ['source' => 'provider_reversal_test'],
    ]);
    $order->forceFill([
        'status' => PayCodeIssuanceFundingOrderStatus::Issued,
        'voucher_id' => $voucher->getKey(),
        'issued_at' => now(),
    ])->saveQuietly();
    $reversal = onDemandFundingReversalObservation($observation);

    app(ReverseSettledFundingIntent::class)->handle($intent->refresh(), $reversal);

    expect($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::Issued)
        ->and($order->voucher_id)->toBe($voucher->getKey())
        ->and(data_get($order->metadata, 'provider_reversal.observation_id'))
        ->toBe($reversal->getKey())
        ->and($order->events()->where(
            'event_type',
            'provider_reversal_recorded',
        )->exists())->toBeTrue();
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

it('falls back to the owning NetBank account for an on-demand bank transfer', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-09-29T14:00:00+00:00'));
    $adapter = new FakeFundingProviderAdapter;
    $adapter->fundingVerificationResolver = static function ($verification): ProviderFundingObservationData {
        if ($verification->fundingAddress === '915008422914050308952') {
            throw new ProviderFundingNotObserved('No incoming QR transaction was observed.');
        }

        expect($verification->fundingAddress)->toBe('113001000019');

        return new ProviderFundingObservationData(
            provider: 'netbank',
            providerTransactionId: '433061247',
            grossAmountMinor: 6_650,
            feeAmountMinor: 0,
            netAmountMinor: 6_650,
            currency: 'PHP',
            providerStatus: 'settled',
            verificationSource: 'netbank-vca-transaction-history',
            payloadHash: hash('sha256', '433061247'),
            fundingAddress: 'sha256:'.hash('sha256', '113001000019'),
            providerAccountReference: 'sha256:'.hash('sha256', '113001000019'),
            occurredAt: new DateTimeImmutable('2026-09-29T13:56:36+00:00'),
            settledAt: new DateTimeImmutable('2026-09-29T13:56:36+00:00'),
            metadata: ['destination_verified' => true],
        );
    };
    $this->app->instance(FakeFundingProviderAdapter::class, $adapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
    $intent = fundingIntentAwaitingOnDemandBankTransfer();

    $verified = app(VerifyFundingIntent::class)->handle(
        $intent,
        new FundingIntentVerificationData(
            trigger: FundingVerificationTrigger::Operator,
            actorId: 'operator-1',
        ),
    );

    expect($verified->status)->toBe(FundingIntentStatus::Verified)
        ->and($verified->provider_transaction_id)->toBe('433061247')
        ->and($adapter->fundingVerifications)->toHaveCount(2)
        ->and($adapter->fundingVerifications[0]->fundingAddress)
        ->toBe('915008422914050308952')
        ->and($adapter->fundingVerifications[1]->fundingAddress)
        ->toBe('113001000019')
        ->and($adapter->fundingVerifications[1]->observedAfter?->format(DATE_ATOM))
        ->toBe('2026-09-29T13:53:00+00:00')
        ->and($adapter->fundingVerifications[1]->observedBefore?->format(DATE_ATOM))
        ->toBe('2026-09-29T14:02:00+00:00')
        ->and(ProviderFundingObservation::query()->sole()->verification_source)
        ->toBe('netbank-corporate-account-transaction-history')
        ->and(data_get(ProviderFundingObservation::query()->sole()->metadata, 'verification_path'))
        ->toBe('corporate_account');

    $this->travelBack();
});

it('routes authoritative amount mismatches to explicit review states', function (
    int $observedAmountMinor,
    PayCodeIssuanceFundingOrderStatus $expectedOrderStatus,
    string $expectedReason,
    string $expectedOrderEvent,
): void {
    $user = actingAsTestUser(0);
    $order = issuanceFundingOrder($user, 'verified-mismatch-'.$observedAmountMinor);
    $order->forceFill([
        'required_amount_minor' => 6_650,
        'on_demand_amount_minor' => 6_650,
        'expected_payment_minor' => 6_650,
    ])->saveQuietly();
    $intent = fundingIntentAwaitingOnDemandBankTransfer();
    $order->forceFill(['funding_intent_id' => $intent->getKey()])->saveQuietly();
    $adapter = new FakeFundingProviderAdapter;
    $adapter->fundingVerificationResolver = static fn (): ProviderFundingObservationData => new ProviderFundingObservationData(
        provider: 'netbank',
        providerTransactionId: 'MISMATCH-'.$observedAmountMinor,
        grossAmountMinor: $observedAmountMinor,
        feeAmountMinor: 0,
        netAmountMinor: $observedAmountMinor,
        currency: 'PHP',
        providerStatus: 'settled',
        verificationSource: 'mismatch-test',
        payloadHash: hash('sha256', 'mismatch-'.$observedAmountMinor),
        fundingAddress: 'sha256:'.hash('sha256', '113001000019'),
        providerAccountReference: 'sha256:'.hash('sha256', '113001000019'),
        occurredAt: now()->subMinute()->toDateTimeImmutable(),
        settledAt: now()->toDateTimeImmutable(),
        metadata: ['destination_verified' => true],
    );
    $this->app->instance(FakeFundingProviderAdapter::class, $adapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);

    $result = app(VerifyFundingIntent::class)->handle(
        $intent,
        new FundingIntentVerificationData(
            trigger: FundingVerificationTrigger::Operator,
            actorId: 'operator-1',
        ),
    );

    expect($result->status)->toBe(FundingIntentStatus::Suspense)
        ->and($result->events()->where('event_type', $expectedReason)->exists())->toBeTrue()
        ->and($order->refresh()->status)->toBe($expectedOrderStatus)
        ->and($order->events()->where('event_type', $expectedOrderEvent)->exists())->toBeTrue()
        ->and($order->voucher_id)->toBeNull()
        ->and(FundingEvidenceClaim::query()->count())->toBe(1);
})->with([
    'underpayment' => [
        6_000,
        PayCodeIssuanceFundingOrderStatus::Underfunded,
        'on_demand_issuance_underpayment',
        'payment_underfunded',
    ],
    'excess payment' => [
        7_000,
        PayCodeIssuanceFundingOrderStatus::PaymentAmbiguous,
        'on_demand_issuance_excess_payment',
        'payment_excess_requires_review',
    ],
]);

it('rejects one provider transaction from funding two issuance orders', function (): void {
    $user = actingAsTestUser(0);
    $firstOrder = issuanceFundingOrder($user, 'exclusive-provider-evidence-first');
    $secondOrder = issuanceFundingOrder($user, 'exclusive-provider-evidence-second');

    foreach ([$firstOrder, $secondOrder] as $order) {
        $order->forceFill([
            'required_amount_minor' => 6_650,
            'on_demand_amount_minor' => 6_650,
            'expected_payment_minor' => 6_650,
        ])->saveQuietly();
    }

    $firstIntent = fundingIntentAwaitingOnDemandBankTransfer();
    $secondIntent = fundingIntentAwaitingOnDemandBankTransfer();
    $firstOrder->forceFill(['funding_intent_id' => $firstIntent->getKey()])->saveQuietly();
    $secondOrder->forceFill(['funding_intent_id' => $secondIntent->getKey()])->saveQuietly();
    $adapter = new FakeFundingProviderAdapter;
    $adapter->fundingVerificationResolver = static fn (): ProviderFundingObservationData => new ProviderFundingObservationData(
        provider: 'netbank',
        providerTransactionId: 'EXCLUSIVE-TX-1',
        grossAmountMinor: 6_650,
        feeAmountMinor: 0,
        netAmountMinor: 6_650,
        currency: 'PHP',
        providerStatus: 'settled',
        verificationSource: 'exclusive-evidence-test',
        payloadHash: hash('sha256', 'exclusive-evidence-payload'),
        fundingAddress: 'sha256:'.hash('sha256', '113001000019'),
        providerAccountReference: 'sha256:'.hash('sha256', '113001000019'),
        occurredAt: now()->subMinute()->toDateTimeImmutable(),
        settledAt: now()->toDateTimeImmutable(),
        metadata: ['destination_verified' => true],
    );
    $this->app->instance(FakeFundingProviderAdapter::class, $adapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
    $verification = new FundingIntentVerificationData(
        trigger: FundingVerificationTrigger::Operator,
        actorId: 'operator-1',
    );

    $first = app(VerifyFundingIntent::class)->handle($firstIntent, $verification);
    $second = app(VerifyFundingIntent::class)->handle($secondIntent, $verification);

    expect($first->status)->toBe(FundingIntentStatus::Verified)
        ->and($second->status)->toBe(FundingIntentStatus::Suspense)
        ->and($second->events()->where(
            'event_type',
            'on_demand_issuance_duplicate_evidence',
        )->exists())->toBeTrue()
        ->and($secondOrder->refresh()->status)
        ->toBe(PayCodeIssuanceFundingOrderStatus::PaymentAmbiguous)
        ->and($secondOrder->events()->where(
            'event_type',
            'duplicate_provider_evidence_rejected',
        )->exists())->toBeTrue()
        ->and(FundingEvidenceClaim::query()->count())->toBe(1);
});

it('does not use corporate account fallback outside on-demand issuance', function (): void {
    $adapter = new FakeFundingProviderAdapter;
    $adapter->fundingVerificationResolver = static function (): never {
        throw new ProviderFundingNotObserved('No incoming QR transaction was observed.');
    };
    $this->app->instance(FakeFundingProviderAdapter::class, $adapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
    $intent = fundingIntentAwaitingOnDemandBankTransfer();
    $intent->forceFill(['purpose' => FundingIntentPurpose::AccountFunding])->saveQuietly();

    $pending = app(VerifyFundingIntent::class)->handle(
        $intent,
        new FundingIntentVerificationData(
            trigger: FundingVerificationTrigger::Operator,
            actorId: 'operator-1',
        ),
    );

    expect($pending->status)->toBe(FundingIntentStatus::AwaitingFunds)
        ->and($adapter->fundingVerifications)->toHaveCount(1)
        ->and($adapter->fundingVerifications[0]->fundingAddress)
        ->toBe('915008422914050308952');
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
        ->and(data_get($projection, 'order.voucher.amount'))->toBe(50.0)
        ->and(data_get($projection, 'order.voucher.currency'))->toBe('PHP')
        ->and(data_get($projection, 'order.voucher.claim_qr'))->toStartWith('data:image/png;base64,')
        ->and(data_get($projection, 'order.voucher.qr_artifacts.direct_claim.kind'))->toBe('pay_code')
        ->and(data_get($projection, 'order.voucher.qr_artifacts.claim_entry.kind'))->toBe('claim_entry')
        ->and(data_get($projection, 'order.voucher.qr_artifacts.claim_entry.identifier'))->toBe('ODIF-4242')
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

function fundingIntentAwaitingOnDemandBankTransfer(): FundingIntent
{
    $identity = (string) Str::uuid();
    $destination = new FundingDestinationData(
        provider: 'netbank',
        mode: 'shared',
        destinationType: 'bank_account',
        accountReference: 'wallet:test',
        displayReference: '•••• 0019 · VCA 91500',
        fingerprint: hash('sha256', 'netbank|113001000019|91500'),
        verificationStatus: 'platform_configured',
        bankAccountNumber: '113-001-00001-9',
        bankAccountName: 'Test Treasury',
        routingAlias: '91500',
    );

    $intent = FundingIntent::query()->create([
        'account_reference' => 'wallet:test',
        'provider_code' => 'netbank',
        'purpose' => FundingIntentPurpose::OnDemandIssuance,
        'expected_amount_minor' => 6_650,
        'currency' => 'PHP',
        'status' => FundingIntentStatus::AwaitingFunds,
        'version' => 1,
        'idempotency_key_hash' => hash('sha256', 'key-'.$identity),
        'idempotency_fingerprint' => hash('sha256', 'fingerprint-'.$identity),
        'created_by_type' => 'test',
        'created_by_id' => 'operator-1',
        'provider_reference' => '915008422914050308952',
        'provider_request_id' => '915008422914050308952',
        'funding_address_ciphertext' => '915008422914050308952',
        'funding_address_hash' => hash('sha256', '915008422914050308952'),
        'instructions_created_at' => now()->subMinutes(5),
        'expires_at' => now()->addMinutes(25),
        'destination_snapshot_ciphertext' => FundingDestinationSnapshot::fromData($destination),
        'destination_fingerprint' => $destination->fingerprint,
        'metadata' => ['source' => 'on_demand_issuance_test'],
    ]);
    $intent->events()->create([
        'sequence' => 1,
        'event_type' => 'provider_instructions_created',
        'from_status' => FundingIntentStatus::PendingInstructions,
        'to_status' => FundingIntentStatus::AwaitingFunds,
        'actor_type' => 'test',
        'actor_id' => 'operator-1',
        'occurred_at' => now()->subMinutes(5),
    ]);

    return $intent;
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

function onDemandFundingReversalObservation(
    ProviderFundingObservation $observation,
): ProviderFundingObservation {
    return ProviderFundingObservation::query()->create([
        'observation_key' => hash('sha256', 'reversal-'.$observation->observation_key),
        'provider_code' => $observation->provider_code,
        'provider_transaction_id' => $observation->provider_transaction_id,
        'provider_operation_id' => 'REV-'.$observation->provider_operation_id,
        'request_id' => $observation->request_id,
        'funding_address' => $observation->funding_address,
        'provider_account_reference' => $observation->provider_account_reference,
        'gross_amount_minor' => $observation->gross_amount_minor,
        'fee_amount_minor' => $observation->fee_amount_minor,
        'net_amount_minor' => $observation->net_amount_minor,
        'currency' => $observation->currency,
        'provider_status' => 'reversed',
        'occurred_at' => now(),
        'verification_source' => 'provider_reversal_test',
        'payload_hash' => hash('sha256', 'reversal-payload-'.$observation->getKey()),
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
