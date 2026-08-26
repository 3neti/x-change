<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Exceptions;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingRequestFailed;
use LBHurtado\PaymentGateway\Funding\NetbankFundingApiClient;
use LBHurtado\PaymentGateway\Funding\NetbankFundingProviderAdapter;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Payment\CreatePaymentAttempt;
use LBHurtado\XChange\Actions\Payment\IssuePaymentInstructions;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Http\Middleware\ShareXChangeBranding;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function (): void {
    $this->withoutMiddleware(ShareXChangeBranding::class);
    config()->set('x-change.funding.providers.netbank.enabled', true);
    config()->set('x-change.payment.attempts.enabled', true);
    config()->set('x-change.payment.attempts.provider', 'netbank');

    $this->paymentAdapter = new FakeFundingProviderAdapter;
    $this->app->instance(FakeFundingProviderAdapter::class, $this->paymentAdapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
});

it('renders a read-only collectible payment page without sensitive instructions', function (): void {
    $voucher = publicPaymentVoucher();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.pay.show', ['code' => strtolower((string) $voucher->code)]))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('component', 'x-change/claim/Payment')
        ->assertJsonPath('props.payment.pay_code', (string) $voucher->code)
        ->assertJsonPath('props.payment.rider_message', null)
        ->assertJsonPath('props.payment.target_amount_minor', 10000)
        ->assertJsonPath('props.payment.collected_amount_minor', 0)
        ->assertJsonPath('props.payment.amount_due_minor', 10000)
        ->assertJsonPath('props.payment.provider', 'netbank')
        ->assertJsonPath('props.payment.can_create_attempt', true)
        ->assertJsonPath('props.payment.attempt', null)
        ->assertJsonPath('props.payment.receipt', null);
});

it('presents the voucher rider message as payer X-Ray context', function (): void {
    $voucher = publicPaymentVoucherForUser(
        actingAsTestUser(),
        'Transportation service for the August field visit.',
    );

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.pay.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath(
            'props.payment.rider_message',
            'Transportation service for the August field visit.',
        );
});

it('creates and reopens exact provider QR instructions in the payer session', function (): void {
    $voucher = publicPaymentVoucher();

    $response = $this->post(route('x-change.pay.attempts.store', [
        'code' => $voucher->code,
    ]));

    $attempt = PaymentAttempt::query()
        ->where('voucher_id', $voucher->getKey())
        ->sole();

    expect($attempt->status)->toBe(PaymentAttemptStatus::AwaitingPayment);

    $response->assertRedirect(route('x-change.pay.show', [
        'code' => $voucher->code,
        'attempt' => $attempt->reference,
    ]));

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.pay.show', [
            'code' => $voucher->code,
            'attempt' => $attempt->reference,
        ]))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('props.payment.attempt.reference', $attempt->reference)
        ->assertJsonPath('props.payment.attempt.status', 'awaiting_payment')
        ->assertJsonPath('props.payment.attempt.amount_minor', 10000)
        ->assertJsonPath('props.payment.attempt.qr_code.mime_type', 'image/png')
        ->assertJsonPath('props.payment.attempt.qr_code.embedded_amount', true)
        ->assertJsonStructure(['props' => ['payment' => ['attempt' => ['qr_code' => ['base64_payload']]]]]);
});

it('sanitizes provider instruction failures and safely retries the same attempt', function (): void {
    $voucher = publicPaymentVoucher();
    $this->paymentAdapter->instructionException = new RuntimeException(
        'secret provider response and credentials',
    );
    Exceptions::fake();

    $this->post(route('x-change.pay.attempts.store', [
        'code' => $voucher->code,
    ]))
        ->assertRedirect(route('x-change.pay.show', ['code' => $voucher->code]))
        ->assertSessionHas(
            'payment_notice',
            'NetBank could not create payment instructions. No payment was recorded. Please try again.',
        );

    $attempt = PaymentAttempt::query()->sole();
    $failure = $attempt->events()->where('event_type', 'provider_instruction_failed')->sole();

    expect($attempt->status)->toBe(PaymentAttemptStatus::PendingInstructions)
        ->and($failure->metadata)->toBe([
            'provider' => 'netbank',
            'retryable' => true,
            'failure_stage' => 'unknown',
        ])
        ->and(json_encode($failure->metadata))->not->toContain('secret provider response');

    Exceptions::assertReported(RuntimeException::class);

    $this->paymentAdapter->instructionException = null;

    $this->post(route('x-change.pay.attempts.store', [
        'code' => $voucher->code,
    ]))->assertRedirect();

    expect(PaymentAttempt::query()->count())->toBe(1)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::AwaitingPayment);
});

it('reports a provisional NetBank QR failure without exposing it to the payer', function (): void {
    config()->set('payment-gateway.netbank.funding.reference_key', 'test-payment-reference-key');
    config()->set('payment-gateway.netbank.funding.pre_transaction_validation_enabled', true);
    config()->set('payment-gateway.netbank.funding.exact_limits_enabled', true);
    $voucher = publicPaymentVoucher();
    $providerFailure = NetbankFundingRequestFailed::forOperation('generate-qrph', 503);
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldNotReceive('generateAliasToken');
    $client->shouldNotReceive('registerPreTransactionReference');
    $client->shouldNotReceive('createExactLimit');
    $client->shouldReceive('generateQrCode')
        ->once()
        ->andThrow($providerFailure);

    $this->app->instance(NetbankFundingApiClient::class, $client);
    $this->app->instance(
        FundingProviderAdapterRegistry::class,
        new FundingProviderAdapterRegistry([
            new NetbankFundingProviderAdapter($client),
        ]),
    );
    Exceptions::fake();

    $this->post(route('x-change.pay.attempts.store', [
        'code' => $voucher->code,
    ]))
        ->assertRedirect(route('x-change.pay.show', ['code' => $voucher->code]))
        ->assertSessionHas(
            'payment_notice',
            'NetBank could not create payment instructions. No payment was recorded. Please try again.',
        );

    $attempt = PaymentAttempt::query()->sole();
    $failure = $attempt->events()->where('event_type', 'provider_instruction_failed')->sole();

    expect($attempt->status)->toBe(PaymentAttemptStatus::PendingInstructions)
        ->and($attempt->provider_reference_hash)->toBeNull()
        ->and($attempt->funding_address_hash)->toBeNull()
        ->and($attempt->instructions_ciphertext)->toBeNull()
        ->and($failure->metadata)->toBe([
            'provider' => 'netbank',
            'retryable' => true,
            'failure_stage' => 'generate-qrph',
        ])
        ->and(json_encode($failure->metadata))->not->toContain('503');

    Exceptions::assertReported(fn (NetbankFundingRequestFailed $exception): bool => $exception === $providerFailure);
});

it('conceals a Payment Attempt owned by another browser session', function (): void {
    $voucher = publicPaymentVoucher();
    $attempt = app(CreatePaymentAttempt::class)->handle(
        $voucher,
        'netbank',
        'different-session',
        'different-request',
    );
    $attempt = app(IssuePaymentInstructions::class)->handle($attempt);

    $this->get(route('x-change.pay.show', [
        'code' => $voucher->code,
        'attempt' => $attempt->reference,
    ]))->assertNotFound();
});

it('checks NetBank history and applies an exact settled payment once', function (): void {
    $user = actingAsTestUser();
    $voucher = publicPaymentVoucherForUser($user);
    $balanceBefore = (float) $user->wallet->balanceFloat;

    $this->post(route('x-change.pay.attempts.store', [
        'code' => $voucher->code,
    ]))->assertRedirect();

    $attempt = PaymentAttempt::query()->sole();
    $this->paymentAdapter->fundingObservation = publicExactPaymentObservation($attempt);

    $response = $this->post(route('x-change.pay.attempts.checks.store', [
        'code' => $voucher->code,
        'attempt' => $attempt->reference,
    ]));

    $response
        ->assertRedirect(route('x-change.pay.show', [
            'code' => $voucher->code,
            'attempt' => $attempt->reference,
        ]))
        ->assertSessionHas('payment_notice', 'Payment confirmed from NetBank history.');

    $this->post(route('x-change.pay.attempts.checks.store', [
        'code' => $voucher->code,
        'attempt' => $attempt->reference,
    ]))->assertRedirect();

    expect($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Settled)
        ->and(VoucherCollection::query()->count())->toBe(1)
        ->and((float) $user->wallet->fresh()->balanceFloat)->toBe($balanceBefore + 100.00);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.pay.show', [
            'code' => $voucher->code,
            'attempt' => $attempt->reference,
        ]))
        ->assertOk()
        ->assertJsonPath('props.payment.is_fully_paid', true)
        ->assertJsonPath('props.payment.amount_due_minor', 0)
        ->assertJsonPath('props.payment.receipt.pay_code', (string) $voucher->code)
        ->assertJsonPath('props.payment.receipt.amount_paid_minor', 10000)
        ->assertJsonPath('props.payment.receipt.currency', 'PHP')
        ->assertJsonPath('props.payment.receipt.payments.0.provider', 'netbank')
        ->assertJsonPath(
            'props.payment.receipt.payments.0.receipt_reference',
            sprintf('PAY-%s-01', $voucher->code),
        )
        ->assertJsonPath('props.payment.receipt.payments.0.amount_paid_minor', 10000)
        ->assertJson(fn ($json) => $json
            ->whereType('props.payment.receipt.completed_at', 'string')
            ->whereType('props.payment.receipt.payments.0.completed_at', 'string')
            ->etc());
});

it('hands collectible payment from the outward claim route to the payment page', function (): void {
    $voucher = publicPaymentVoucher();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/PaymentHandoff')
        ->assertJsonPath('props.code', (string) $voucher->code)
        ->assertJsonPath('props.payment_url', route('x-change.pay.show', ['code' => $voucher->code]))
        ->assertJsonPath('props.is_fully_collected', false);
});

function publicPaymentVoucher(): Voucher
{
    return publicPaymentVoucherForUser(actingAsTestUser());
}

function publicPaymentVoucherForUser(User $user, ?string $riderMessage = null): Voucher
{
    return issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => 100.00,
            'rider' => [
                'message' => $riderMessage,
            ],
            'metadata' => [
                'flow_type' => 'collectible',
                'issuer_id' => (string) $user->id,
                'collection_wallet_id' => $user->wallet->id,
            ],
        ],
    ));
}

function publicExactPaymentObservation(PaymentAttempt $attempt): ProviderFundingObservationData
{
    return new ProviderFundingObservationData(
        provider: $attempt->provider_code,
        providerTransactionId: 'public-payment-transaction-'.str()->uuid(),
        grossAmountMinor: $attempt->expected_amount_minor,
        feeAmountMinor: 0,
        netAmountMinor: $attempt->expected_amount_minor,
        currency: $attempt->currency,
        providerStatus: 'settled',
        verificationSource: 'fake-authoritative-vca-history',
        payloadHash: hash('sha256', 'public-payment-observation-'.str()->uuid()),
        fundingAddress: 'sha256:'.hash('sha256', (string) $attempt->funding_address_ciphertext),
        occurredAt: new DateTimeImmutable('2026-07-24T04:59:00+00:00'),
        settledAt: new DateTimeImmutable('2026-07-24T05:00:00+00:00'),
        metadata: [
            'destination_verified' => true,
        ],
    );
}
