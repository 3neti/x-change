<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Payment\CreatePaymentAttempt;
use LBHurtado\XChange\Actions\Payment\IssuePaymentInstructions;
use LBHurtado\XChange\Actions\Payment\RecordVoucherCollection;
use LBHurtado\XChange\Actions\Payment\VerifyPaymentAttempt;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;
use LBHurtado\XChange\Tests\Fakes\User;

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
        browserKey: 'payer-session-1',
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

it('settles one exact provider observation into one voucher collection', function (): void {
    $user = actingAsTestUser();
    $voucher = paymentAttemptCollectibleVoucherForUser($user);
    $balanceBefore = (float) $user->wallet->balanceFloat;
    $attempt = issuedPaymentAttempt($voucher);
    $this->paymentAdapter->fundingObservation = exactPaymentObservation($attempt);

    $settled = app(VerifyPaymentAttempt::class)->handle(
        $attempt,
        PaymentVerificationTrigger::Payer,
    );
    $replay = app(VerifyPaymentAttempt::class)->handle(
        $attempt,
        PaymentVerificationTrigger::Payer,
    );

    expect($settled->status)->toBe(PaymentAttemptStatus::Settled)
        ->and($settled->voucher_collection_id)->not->toBeNull()
        ->and($replay->status)->toBe(PaymentAttemptStatus::Settled)
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->getKey())->count())->toBe(1)
        ->and((float) $user->wallet->fresh()->balanceFloat)->toBe($balanceBefore + 100.00)
        ->and(DB::table('x_change_funding_intents')->count())->toBe(0)
        ->and(DB::table('x_change_account_funding_receipts')->count())->toBe(0);
});

it('keeps pending provider history awaiting payment without collection', function (): void {
    $user = actingAsTestUser();
    $voucher = paymentAttemptCollectibleVoucherForUser($user);
    $balanceBefore = (float) $user->wallet->balanceFloat;
    $attempt = issuedPaymentAttempt($voucher);
    $this->paymentAdapter->fundingObservation = exactPaymentObservation(
        $attempt,
        status: 'pending',
        settledAt: null,
    );

    $checked = app(VerifyPaymentAttempt::class)->handle(
        $attempt,
        PaymentVerificationTrigger::Payer,
    );

    expect($checked->status)->toBe(PaymentAttemptStatus::AwaitingPayment)
        ->and($checked->last_checked_at)->not->toBeNull()
        ->and(VoucherCollection::query()->count())->toBe(0)
        ->and((float) $user->wallet->fresh()->balanceFloat)->toBe($balanceBefore);
});

it('moves mismatched authoritative payment evidence to suspense', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = issuedPaymentAttempt($voucher);
    $this->paymentAdapter->fundingObservation = exactPaymentObservation(
        $attempt,
        amountMinor: 9900,
    );

    $checked = app(VerifyPaymentAttempt::class)->handle(
        $attempt,
        PaymentVerificationTrigger::Payer,
    );

    expect($checked->status)->toBe(PaymentAttemptStatus::Suspense)
        ->and($checked->events->last()->event_type)->toBe('provider_payment_mismatch')
        ->and(VoucherCollection::query()->count())->toBe(0);
});

function paymentAttemptCollectibleVoucher(): Voucher
{
    return paymentAttemptCollectibleVoucherForUser(actingAsTestUser());
}

function paymentAttemptCollectibleVoucherForUser(User $user): Voucher
{
    return issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
                'issuer_id' => (string) $user->id,
                'collection_wallet_id' => $user->wallet->id,
            ],
        ],
    ));
}

function issuedPaymentAttempt(Voucher $voucher): PaymentAttempt
{
    $attempt = app(CreatePaymentAttempt::class)->handle(
        $voucher,
        'netbank',
        'payer-session-1',
        'request-'.str()->uuid(),
    );

    return app(IssuePaymentInstructions::class)->handle($attempt);
}

function exactPaymentObservation(
    PaymentAttempt $attempt,
    string $status = 'settled',
    ?DateTimeImmutable $settledAt = new DateTimeImmutable('2026-07-24T05:00:00+00:00'),
    ?int $amountMinor = null,
): ProviderFundingObservationData {
    $amountMinor ??= $attempt->expected_amount_minor;

    return new ProviderFundingObservationData(
        provider: $attempt->provider_code,
        providerTransactionId: 'payment-transaction-'.str()->uuid(),
        grossAmountMinor: $amountMinor,
        feeAmountMinor: 0,
        netAmountMinor: $amountMinor,
        currency: $attempt->currency,
        providerStatus: $status,
        verificationSource: 'fake-authoritative-vca-history',
        payloadHash: hash('sha256', 'payment-observation-'.str()->uuid()),
        fundingAddress: 'sha256:'.hash('sha256', (string) $attempt->funding_address_ciphertext),
        occurredAt: new DateTimeImmutable('2026-07-24T04:59:00+00:00'),
        settledAt: $settledAt,
        metadata: [
            'destination_verified' => true,
        ],
    );
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
