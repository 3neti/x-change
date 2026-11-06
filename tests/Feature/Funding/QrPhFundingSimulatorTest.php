<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookReceiptData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookRequestData;
use LBHurtado\EmiCore\Exceptions\ProviderFundingVerificationIndeterminate;
use LBHurtado\XChange\Actions\Funding\CreateFundingIntent;
use LBHurtado\XChange\Actions\Funding\IssueFundingInstructions;
use LBHurtado\XChange\Actions\Funding\SimulateQrPhPayment;
use LBHurtado\XChange\Actions\Payment\ApplyReviewedPayment;
use LBHurtado\XChange\Actions\Payment\CreatePaymentAttempt;
use LBHurtado\XChange\Actions\Payment\CreditReviewedExcess;
use LBHurtado\XChange\Actions\Payment\IssuePaymentInstructions;
use LBHurtado\XChange\Actions\Payment\MonitorPaymentAttempt;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Jobs\Payment\VerifyAdditionalQrPaymentJob;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\SimulatedFundingTransaction;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Funding\Base64PngQrPhFundingSimulationQrRenderer;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Services\Funding\QrPhSimulatorFundingProviderAdapter;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;
use LBHurtado\XChange\Support\Funding\QrPhFundingSimulatorGuard;

beforeEach(function () {
    config()->set('x-change.funding.simulator.enabled', true);
    config()->set('x-change.funding.providers.qrph_simulator.enabled', true);
    config()->set('x-change.funding.simulator.signing_key', 'simulator-signing-key');
    config()->set('x-change.funding.simulator.mobile_hash_key', 'simulator-mobile-hash-key');
});

it('ships the package-owned simulator transaction table', function () {
    expect(Schema::hasColumns('x_change_simulated_funding_transactions', [
        'provider_request_id',
        'provider_transaction_id',
        'provider_event_id',
        'payer_mobile_ciphertext',
        'payer_mobile_hash',
        'gross_amount_minor',
        'currency',
        'status',
        'payload_hash',
        'settled_at',
    ]))->toBeTrue();
});

it('registers a guarded QR Ph funding simulator adapter', function () {
    $adapter = app(FundingProviderAdapterRegistry::class)->for('qrph_simulator');

    expect($adapter)->toBeInstanceOf(QrPhSimulatorFundingProviderAdapter::class)
        ->and($adapter->providerCode())->toBe('qrph_simulator');
});

it('creates deterministic local-only QR Ph instructions', function () {
    $instructions = app(QrPhSimulatorFundingProviderAdapter::class)
        ->createFundingInstructions(new FundingInstructionRequestData(
            provider: 'qrph_simulator',
            fundingReference: '01K123456789',
            amountMinor: 2_500,
            currency: 'PHP',
            accountReference: 'wallet:123',
        ));

    expect($instructions->providerReference)->toBe('QRSIM-'.strtoupper(substr(hash('sha256', '01K123456789'), 0, 24)))
        ->and($instructions->fundingAddress)->toBe('qrph-simulator:'.$instructions->providerReference)
        ->and($instructions->amountMinor)->toBe(2_500)
        ->and($instructions->displayData)->toMatchArray([
            'institution' => 'QR Ph Simulator',
            'delivery' => 'local-simulation-only',
        ])
        ->and($instructions->qrCode?->mimeType)->toBe('image/png')
        ->and($instructions->qrCode?->qrMode)->toBe('dynamic')
        ->and($instructions->qrCode?->transactionType)->toBe('p2m')
        ->and($instructions->qrCode?->embeddedAmount)->toBeTrue()
        ->and($instructions->qrCode?->providerGenerated)->toBeFalse()
        ->and(base64_decode((string) $instructions->qrCode?->base64Payload, true))->toStartWith("\x89PNG");
});

it('records a signed simulated payment and independently verifies its provider ledger evidence', function () {
    $intent = app(CreateFundingIntent::class)->handle(new CreateFundingIntentData(
        accountReference: 'wallet:123',
        provider: 'qrph_simulator',
        expectedAmountMinor: 2_500,
        currency: 'PHP',
        idempotencyKey: 'qrph-simulator-test',
        actorType: 'test',
        actorId: '123',
    ));
    $intent = app(IssueFundingInstructions::class)->handle($intent, 'test', '123');
    $payment = app(SimulateQrPhPayment::class)->handle($intent, '0917 123 4567');
    $request = new ProviderWebhookRequestData(
        provider: 'qrph_simulator',
        rawBody: $payment->rawBody,
        contentType: 'application/json',
        headers: [],
        sourceIp: '127.0.0.1',
        receivedAt: new DateTimeImmutable,
        signature: $payment->signature,
    );
    $adapter = app(QrPhSimulatorFundingProviderAdapter::class);
    $authentication = $adapter->authenticateWebhook($request);
    $hint = $adapter->normalizeWebhook(
        ProviderWebhookReceiptData::fromRequest($request, $authentication),
    );
    $observation = $adapter->verifyFunding(new FundingVerificationData(
        provider: 'qrph_simulator',
        fundingIntentReference: $intent->reference,
        expectedAmountMinor: $intent->expected_amount_minor,
        currency: $intent->currency,
        providerRequestId: $intent->provider_request_id,
        fundingAddress: $intent->funding_address_ciphertext,
    ));

    expect($authentication->authenticated)->toBeTrue()
        ->and($hint->providerEventId)->toBe($payment->transaction->provider_event_id)
        ->and($hint->requestId)->toBe($intent->provider_request_id)
        ->and($observation->providerStatus)->toBe('settled')
        ->and($observation->grossAmountMinor)->toBe(2_500)
        ->and($observation->metadata['destination_verified'])->toBeTrue()
        ->and($observation->payerIdentity?->mobile)->toBe('639171234567')
        ->and($observation->payerIdentity?->providerVerified)->toBeTrue()
        ->and(SimulatedFundingTransaction::query()->count())->toBe(1);
});

it('rejects invalid simulator signatures', function () {
    $request = new ProviderWebhookRequestData(
        provider: 'qrph_simulator',
        rawBody: '{"event_id":"QRSIM-EVT-1"}',
        contentType: 'application/json',
        headers: [],
        sourceIp: '127.0.0.1',
        receivedAt: new DateTimeImmutable,
        signature: 'invalid',
    );

    expect(app(QrPhSimulatorFundingProviderAdapter::class)->authenticateWebhook($request)->authenticated)
        ->toBeFalse();
});

it('observes and verifies distinct payments on one simulated QR without duplicating a replay', function (): void {
    try {
        $issuer = actingAsTestUser();
        $voucher = issueVoucher(validVoucherInstructions(amount: 0, overrides: [
            'target_amount' => 60,
            'metadata' => ['flow_type' => 'collectible', 'issuer_id' => (string) $issuer->id, 'collection_wallet_id' => $issuer->wallet->id],
        ]));
        $attempt = app(IssuePaymentInstructions::class)->handle(app(CreatePaymentAttempt::class)->handle(
            $voucher, 'qrph_simulator', 'simulated-session', 'simulated-request',
        ));
        PaymentAttempt::query()->whereKey($attempt->id)->update(['expected_amount_minor' => 2000]);
        $attempt = $attempt->fresh();
        $simulator = app(SimulateQrPhPayment::class);
        $first = $simulator->handlePaymentAttempt($attempt, '09171234567', paymentKey: 'payment-1');
        $second = $simulator->handlePaymentAttempt($attempt, '09171234567', paymentKey: 'payment-2');
        $replay = $simulator->handlePaymentAttempt($attempt, '09171234567', paymentKey: 'payment-1');
        $verification = new FundingVerificationData(
            provider: 'qrph_simulator', fundingIntentReference: $attempt->reference,
            expectedAmountMinor: $attempt->expected_amount_minor, currency: $attempt->currency,
            providerRequestId: $attempt->provider_request_id_ciphertext, fundingAddress: $attempt->funding_address_ciphertext,
            observedAfter: $attempt->instructions_created_at->toDateTimeImmutable(), observedBefore: now()->addSecond()->toDateTimeImmutable(),
        );
        $adapter = app(QrPhSimulatorFundingProviderAdapter::class);
        $payments = $adapter->incomingPayments($verification);
        expect($first->transaction->is($replay->transaction))->toBeTrue()
            ->and($first->transaction->is($second->transaction))->toBeFalse()
            ->and($payments)->toHaveCount(2)
            ->and($adapter->verifyIncomingPayment($verification, $second->transaction->provider_transaction_id)->providerTransactionId)->toBe($second->transaction->provider_transaction_id)
            ->and($adapter->verifyIncomingPayment($verification, $second->transaction->provider_transaction_id)->metadata['destination_verified'])->toBeTrue();
        expect(fn () => $adapter->verifyFunding($verification))->toThrow(ProviderFundingVerificationIndeterminate::class);
        Queue::fake();
        expect(app(MonitorPaymentAttempt::class)->handle($attempt))->toBe(2);
        foreach ($attempt->observedPayments()->get() as $observed) {
            app()->call([new VerifyAdditionalQrPaymentJob($observed->id, 'qrph_simulator'), 'handle']);
        }
        expect(VoucherCollection::query()->where('voucher_id', $voucher->id)->sum('collected_amount_minor'))->toBe(4000)
            ->and(app(VoucherCollectionProgressService::class)->compute($voucher->fresh())->remaining_to_collect_minor)->toBe(2000);
        $third = $simulator->handlePaymentAttempt($attempt->fresh(), '09171234567', paymentKey: 'payment-3');
        app(MonitorPaymentAttempt::class)->handle($attempt->fresh());
        $thirdObserved = $attempt->observedPayments()->where('provider_transaction_hash', hash('sha256', $third->transaction->provider_transaction_id))->sole();
        app()->call([new VerifyAdditionalQrPaymentJob($thirdObserved->id, 'qrph_simulator'), 'handle']);
        $excess = $simulator->handlePaymentAttempt($attempt->fresh(), '09171234567', paymentKey: 'payment-4');
        app(MonitorPaymentAttempt::class)->handle($attempt->fresh());
        $excessObserved = $attempt->observedPayments()->where('provider_transaction_hash', hash('sha256', $excess->transaction->provider_transaction_id))->sole();
        app()->call([new VerifyAdditionalQrPaymentJob($excessObserved->id, 'qrph_simulator'), 'handle']);
        expect(VoucherCollection::query()->where('voucher_id', $voucher->id)->sum('collected_amount_minor'))->toBe(6000)
            ->and(DB::table('x_change_payment_transaction_reviews')->where('observed_payment_transaction_id', $excessObserved->id)->value('status'))->toBe('open');
        $reviewId = DB::table('x_change_payment_transaction_reviews')->where('observed_payment_transaction_id', $excessObserved->id)->value('id');
        $credit = app(CreditReviewedExcess::class)->handle($reviewId, $issuer, 'Verified simulated excess payment');
        expect($credit->fresh()->status->value)->toBe('settled')
            ->and(app(CreditReviewedExcess::class)->handle($reviewId, $issuer, 'Verified simulated excess payment')->id)->toBe($credit->id);
    } finally {
        DB::table('x_change_simulated_funding_transactions')->delete();
    }
});

it('resolves a simulated shared-destination payment to a reviewed candidate once', function (): void {
    Queue::fake();
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(amount: 0, overrides: [
        'target_amount' => 100,
        'metadata' => ['flow_type' => 'collectible', 'issuer_id' => (string) $issuer->id, 'collection_wallet_id' => $issuer->wallet->id],
    ]));
    $first = app(IssuePaymentInstructions::class)->handle(app(CreatePaymentAttempt::class)->handle($voucher, 'qrph_simulator', 'shared-first', 'shared-first-request'));
    $second = app(IssuePaymentInstructions::class)->handle(app(CreatePaymentAttempt::class)->handle($voucher, 'qrph_simulator', 'shared-second', 'shared-second-request'));
    PaymentAttempt::query()->whereKey($second->id)->update([
        'provider_request_id_ciphertext' => $first->getRawOriginal('provider_request_id_ciphertext'),
        'funding_address_ciphertext' => $first->getRawOriginal('funding_address_ciphertext'),
    ]);
    $second = $second->fresh();
    app(SimulateQrPhPayment::class)->handlePaymentAttempt($first, '09171234567');
    app(MonitorPaymentAttempt::class)->handle($first);
    app(MonitorPaymentAttempt::class)->handle($second);
    $reviewId = DB::table('x_change_payment_transaction_reviews')->value('id');
    $collection = app(ApplyReviewedPayment::class)->handle($reviewId, $second->id, $issuer, 'Matched the simulated payer evidence');
    $again = app(ApplyReviewedPayment::class)->handle($reviewId, $second->id, $issuer, 'Matched the simulated payer evidence');
    expect($collection->is($again))->toBeTrue()
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(1)
        ->and(DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->value('resolution_payment_attempt_id'))->toBe($second->id);
});

it('refuses the simulator when its feature gate is disabled', function () {
    config()->set('x-change.funding.simulator.enabled', false);

    expect(fn () => app(QrPhSimulatorFundingProviderAdapter::class)
        ->createFundingInstructions(new FundingInstructionRequestData(
            provider: 'qrph_simulator',
            fundingReference: '01K123456789',
            amountMinor: 2_500,
            currency: 'PHP',
            accountReference: 'wallet:123',
        )))->toThrow(LogicException::class, 'unavailable');
});

it('refuses the simulator in production even when its feature flag is enabled', function () {
    $application = Mockery::mock(Application::class);
    $application->shouldReceive('isProduction')->once()->andReturnTrue();
    $adapter = new QrPhSimulatorFundingProviderAdapter(
        new QrPhFundingSimulatorGuard($application),
        app(Base64PngQrPhFundingSimulationQrRenderer::class),
    );

    expect(fn () => $adapter
        ->createFundingInstructions(new FundingInstructionRequestData(
            provider: 'qrph_simulator',
            fundingReference: '01K123456789',
            amountMinor: 2_500,
            currency: 'PHP',
            accountReference: 'wallet:123',
        )))->toThrow(LogicException::class, 'unavailable');
});
