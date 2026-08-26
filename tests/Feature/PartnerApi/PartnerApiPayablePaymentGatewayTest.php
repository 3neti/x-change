<?php

declare(strict_types=1);

use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\PartnerApi\CreatePartnerApiClient;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Models\PartnerApiOperation;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function (): void {
    config()->set('x-change.partner_api.enabled', true);
    config()->set('x-change.payment.attempts.enabled', true);
    config()->set('x-change.payment.attempts.provider', 'netbank');
    config()->set('x-change.funding.providers.netbank.enabled', true);

    $this->partnerPaymentAdapter = new FakeFundingProviderAdapter;
    $this->app->instance(FakeFundingProviderAdapter::class, $this->partnerPaymentAdapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
});

it('creates payable payment instructions once and replays a durable response', function (): void {
    [$issuer] = partnerPayableGatewayAuthenticate(['pay-codes:pay']);
    $voucher = partnerPayableGatewayVoucher($issuer, 'GATE-PAY');
    $headers = [
        'Idempotency-Key' => 'gateway-payment-001',
        'X-Correlation-ID' => 'gateway-run-001',
    ];

    $this->postJson("/api/partner/v1/pay-codes/{$voucher->code}/payment-attempts", [], $headers)
        ->assertCreated()
        ->assertHeader('X-Correlation-ID', 'gateway-run-001')
        ->assertJsonPath('data.schema', 'x-change.partner-payment-attempt.v1')
        ->assertJsonPath('data.code', 'GATE-PAY')
        ->assertJsonPath('data.external_reference', 'BPLS-GATE-PAY')
        ->assertJsonPath('data.consumer_status', 'processing')
        ->assertJsonPath('data.attempt.status', 'awaiting_payment')
        ->assertJsonPath('data.attempt.amount_minor', 10000)
        ->assertJsonPath('data.attempt.currency', 'PHP')
        ->assertJsonPath('data.pay_url', route('x-change.pay.show', ['code' => 'GATE-PAY']))
        ->assertJsonPath('meta.idempotency.replayed', false)
        ->assertJsonStructure(['data' => ['attempt' => ['qr_code' => ['base64_payload']]]]);

    $this->postJson("/api/partner/v1/pay-codes/{$voucher->code}/payment-attempts", [], $headers)
        ->assertOk()
        ->assertJsonPath('meta.idempotency.replayed', true)
        ->assertJsonPath('data.attempt.reference', PaymentAttempt::query()->sole()->reference);

    partnerPayableGatewayCollection($voucher, 10000);

    $this->postJson("/api/partner/v1/pay-codes/{$voucher->code}/payment-attempts", [], $headers)
        ->assertOk()
        ->assertJsonPath('meta.idempotency.replayed', true)
        ->assertJsonPath('data.attempt.reference', PaymentAttempt::query()->sole()->reference);

    $this->postJson("/api/partner/v1/pay-codes/{$voucher->code}/payment-attempts", [
        'provider' => 'manual',
    ], $headers)
        ->assertConflict()
        ->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');

    expect($this->partnerPaymentAdapter->instructionCalls)->toBe(1)
        ->and(PaymentAttempt::query()->count())->toBe(1)
        ->and(PartnerApiOperation::query()->where('operation', 'payment_attempt_created')->count())->toBe(1);
});

it('sanitizes provider failures while retaining retryable attempt evidence', function (): void {
    [$issuer] = partnerPayableGatewayAuthenticate(['pay-codes:pay']);
    $voucher = partnerPayableGatewayVoucher($issuer, 'GATE-FAIL');
    $this->partnerPaymentAdapter->instructionException = new RuntimeException(
        'private upstream credentials and provider response',
    );

    $response = $this->postJson("/api/partner/v1/pay-codes/{$voucher->code}/payment-attempts", [], [
        'Idempotency-Key' => 'gateway-failure-001',
        'X-Correlation-ID' => 'gateway-failure-run-001',
    ])->assertStatus(503)
        ->assertHeader('X-Correlation-ID', 'gateway-failure-run-001')
        ->assertJsonPath('code', 'PAYMENT_INSTRUCTIONS_UNAVAILABLE')
        ->assertJsonPath('message', 'Payment instructions are temporarily unavailable.');

    expect($response->getContent())->not->toContain('private upstream')
        ->and(PaymentAttempt::query()->count())->toBe(1)
        ->and(PaymentAttempt::query()->sole()->events()->where('event_type', 'provider_instruction_failed')->count())->toBe(1)
        ->and(PartnerApiOperation::query()->count())->toBe(0);
});

it('requires the payable scope and conceals another issuer voucher', function (): void {
    [$issuer] = partnerPayableGatewayAuthenticate(['pay-codes:read']);
    $other = User::query()->create([
        'name' => 'Other payable issuer',
        'email' => 'other-payable-'.str()->uuid().'@example.test',
        'password' => 'password',
    ]);
    fundTestUserWallet($other);
    $owned = partnerPayableGatewayVoucher($issuer, 'GATE-SCOPE');
    $foreign = partnerPayableGatewayVoucher($other, 'GATE-FOREIGN');

    $this->postJson("/api/partner/v1/pay-codes/{$owned->code}/payment-attempts", [], [
        'Idempotency-Key' => 'gateway-scope-001',
    ])->assertForbidden();

    partnerPayableGatewayAuthenticateExisting($issuer, ['pay-codes:pay']);

    $this->postJson("/api/partner/v1/pay-codes/{$foreign->code}/payment-attempts", [], [
        'Idempotency-Key' => 'gateway-foreign-001',
    ])->assertNotFound();

    expect(PaymentAttempt::query()->count())->toBe(0);
});

it('rejects a fully paid voucher through the collection capability error', function (): void {
    [$issuer] = partnerPayableGatewayAuthenticate(['pay-codes:pay']);
    $voucher = partnerPayableGatewayVoucher($issuer, 'GATE-PAID');
    partnerPayableGatewayCollection($voucher, 10000);

    $this->postJson("/api/partner/v1/pay-codes/{$voucher->code}/payment-attempts", [], [
        'Idempotency-Key' => 'gateway-paid-001',
    ])->assertUnprocessable()
        ->assertJsonPath('code', 'VOUCHER_CANNOT_COLLECT')
        ->assertJsonPath('errors.type', 'capability_violation');

    expect(PaymentAttempt::query()->count())->toBe(0)
        ->and($this->partnerPaymentAdapter->instructionCalls)->toBe(0);
});

it('enriches payable reads with payment links progress and a completed receipt', function (): void {
    [$issuer] = partnerPayableGatewayAuthenticate(['pay-codes:read']);
    $voucher = partnerPayableGatewayVoucher($issuer, 'GATE-READ');
    partnerPayableGatewayCollection($voucher, 10000);
    $lifecycle = Mockery::mock(VoucherLifecycleServiceContract::class);
    $lifecycle->shouldReceive('show')->once()->andReturn([
        'code' => 'GATE-READ',
        'amount' => 0,
        'currency' => 'PHP',
        'operational_status' => ['key' => 'paid'],
        'capability' => ['key' => 'collection'],
        'claimed' => false,
        'fully_claimed' => false,
    ]);
    $this->app->instance(VoucherLifecycleServiceContract::class, $lifecycle);

    $this->getJson("/api/partner/v1/pay-codes/{$voucher->code}")
        ->assertOk()
        ->assertJsonPath('data.links.pay_path', '/x/pay/GATE-READ')
        ->assertJsonPath('data.collection.target_amount_minor', 10000)
        ->assertJsonPath('data.collection.remaining_to_collect_minor', 0)
        ->assertJsonPath('data.collection.is_fully_collected', true)
        ->assertJsonPath('data.receipt.amount_paid_minor', 10000)
        ->assertJsonPath('data.receipt.currency', 'PHP')
        ->assertJsonMissingPath('data.receipt.payments.0.provider_transaction_id');
});

/** @return array{User, mixed} */
function partnerPayableGatewayAuthenticate(array $scopes): array
{
    $issuer = actingAsTestUser();

    return [$issuer, partnerPayableGatewayAuthenticateExisting($issuer, $scopes)];
}

function partnerPayableGatewayAuthenticateExisting(User $issuer, array $scopes): mixed
{
    $credential = app(CreatePartnerApiClient::class)->handle(
        name: 'Payable Gateway Sandbox',
        issuer: $issuer,
        scopes: $scopes,
    );
    Passport::actingAsClient(Client::query()->findOrFail($credential->client_id), $scopes);

    return $credential;
}

function partnerPayableGatewayVoucher(User $issuer, string $code): Voucher
{
    $voucher = new Voucher([
        'code' => $code,
        'metadata' => [
            'instructions' => [
                'cash' => ['amount' => 0.0, 'currency' => 'PHP'],
                'target_amount' => 100.0,
                'metadata' => [
                    'custom' => ['external_reference' => 'BPLS-'.$code],
                ],
            ],
            'flow_type' => 'collectible',
            'issuer_id' => (string) $issuer->getKey(),
            'collection_wallet_id' => (string) $issuer->wallet->getKey(),
        ],
        'state' => 'active',
    ]);
    $voucher->owner()->associate($issuer);
    $voucher->save();

    return $voucher;
}

function partnerPayableGatewayCollection(Voucher $voucher, int $amountMinor): VoucherCollection
{
    return VoucherCollection::query()->create([
        'voucher_id' => $voucher->getKey(),
        'collection_number' => 1,
        'status' => 'collected',
        'requested_amount_minor' => $amountMinor,
        'collected_amount_minor' => $amountMinor,
        'currency' => 'PHP',
        'provider' => 'netbank',
        'provider_reference' => 'partner-payable-gateway',
        'provider_transaction_id' => 'partner-payable-gateway-'.str()->uuid(),
        'idempotency_key' => 'partner-payable-gateway:'.str()->uuid(),
        'completed_at' => now(),
    ]);
}
