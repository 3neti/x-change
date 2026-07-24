<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Payment\CreatePaymentAttempt;
use LBHurtado\XChange\Actions\Payment\IssuePaymentInstructions;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Http\Middleware\ShareXChangeBranding;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;

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
        ->assertJsonPath('component', 'x-change/payment/Show')
        ->assertJsonPath('props.payment.pay_code', (string) $voucher->code)
        ->assertJsonPath('props.payment.amount_due_minor', 10000)
        ->assertJsonPath('props.payment.provider', 'netbank')
        ->assertJsonPath('props.payment.can_create_attempt', true)
        ->assertJsonPath('props.payment.attempt', null);
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

it('does not expose collectible payment on the outward claim route', function (): void {
    $voucher = publicPaymentVoucher();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/Error')
        ->assertJsonPath('props.message', 'This Pay Code accepts payment and cannot be claimed.');
});

function publicPaymentVoucher(): Voucher
{
    actingAsTestUser();

    return issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
                'issuer_id' => (string) auth()->id(),
            ],
        ],
    ));
}
