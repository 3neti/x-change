<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Payment\CreatePaymentAttempt;
use LBHurtado\XChange\Actions\Payment\IssuePaymentInstructions;
use LBHurtado\XChange\Actions\Payment\RecordVoucherCollection;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;

beforeEach(function (): void {
    config()->set('x-change.funding.providers.netbank.enabled', true);
    config()->set('x-change.payment.attempts.expires_after_minutes', 15);

    $this->paymentAdapter = new FakeFundingProviderAdapter;
    $this->app->instance(FakeFundingProviderAdapter::class, $this->paymentAdapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
});

it('creates an exact Payment Attempt for the collectible balance', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();

    recordPaymentAttemptCollection($voucher, 25.00);

    $attempt = app(CreatePaymentAttempt::class)->handle(
        voucher: $voucher,
        provider: 'netbank',
        sessionId: 'payer-session-1',
        idempotencyKey: 'request-1',
    );

    expect($attempt->status)->toBe(PaymentAttemptStatus::PendingInstructions)
        ->and($attempt->expected_amount_minor)->toBe(7500)
        ->and($attempt->currency)->toBe('PHP')
        ->and($attempt->session_key_hash)->toHaveLength(64)
        ->and($attempt->session_key_hash)->not->toContain('payer-session-1')
        ->and($attempt->events)->toHaveCount(1)
        ->and($attempt->events->first()->event_type)->toBe('created');
});

it('replays creation idempotently without creating a second attempt', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $create = app(CreatePaymentAttempt::class);

    $first = $create->handle($voucher, 'netbank', 'payer-session-1', 'request-1');
    $replay = $create->handle($voucher, 'netbank', 'payer-session-1', 'request-1');

    expect($replay->is($first))->toBeTrue()
        ->and(PaymentAttempt::query()->where('voucher_id', $voucher->getKey())->count())->toBe(1);
});

it('issues encrypted provider QR instructions once without Account funding', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = app(CreatePaymentAttempt::class)->handle(
        $voucher,
        'netbank',
        'payer-session-1',
        'request-1',
    );

    $issued = app(IssuePaymentInstructions::class)->handle($attempt);
    $retry = app(IssuePaymentInstructions::class)->handle($attempt);
    $raw = DB::table('x_change_payment_attempts')->find($attempt->getKey());

    expect($issued->status)->toBe(PaymentAttemptStatus::AwaitingPayment)
        ->and($issued->version)->toBe(2)
        ->and($issued->instructions_ciphertext['qr_code'])->toMatchArray([
            'mime_type' => 'image/png',
            'qr_mode' => 'dynamic',
            'transaction_type' => 'p2m',
            'embedded_amount' => true,
            'provider_generated' => true,
        ])
        ->and($issued->provider_request_id_ciphertext)->toBe('915001234567890123456')
        ->and($raw->provider_request_id_ciphertext)->not->toContain('915001234567890123456')
        ->and($raw->instructions_ciphertext)->not->toContain('iVBORw0KGgo')
        ->and($retry->getKey())->toBe($issued->getKey())
        ->and($retry->events)->toHaveCount(2)
        ->and($this->paymentAdapter->instructionCalls)->toBe(1)
        ->and(DB::table('x_change_funding_intents')->count())->toBe(0)
        ->and(DB::table('x_change_account_funding_receipts')->count())->toBe(0);
});

function paymentAttemptCollectibleVoucher(): Voucher
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

function recordPaymentAttemptCollection(Voucher $voucher, float $amount): void
{
    app(RecordVoucherCollection::class)->handle(
        voucher: $voucher,
        result: new VoucherPaymentResultData(
            voucher_code: (string) $voucher->code,
            status: 'collected',
            amount: $amount,
            currency: 'PHP',
            provider: 'test',
            provider_reference: 'reference-'.str()->uuid(),
            provider_transaction_id: 'transaction-'.str()->uuid(),
        ),
        payload: [
            'amount' => $amount,
        ],
    );
}
