<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookReceiptData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookRequestData;
use LBHurtado\XChange\Actions\Funding\CreateFundingIntent;
use LBHurtado\XChange\Actions\Funding\IssueFundingInstructions;
use LBHurtado\XChange\Actions\Funding\SimulateQrPhPayment;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Models\SimulatedFundingTransaction;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Services\Funding\QrPhSimulatorFundingProviderAdapter;
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
        ]);
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
