<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function (): void {
    config()->set('x-change.lifecycle.defaults.user_model', User::class);
    config()->set('x-change.lifecycle.defaults.system_user_email', 'system@example.test');
    config()->set('x-change.lifecycle.defaults.test_user_email', 'lester@hurtado.ph');
    config()->set('x-change.lifecycle.defaults.test_user_mobile', '09173011987');
    config()->set('x-change.funding.providers.netbank.enabled', true);
    config()->set('x-change.payment.attempts.hash_key', 'payment-voucher-scenario-test-key');
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');

    $adapter = new FakeFundingProviderAdapter;
    app()->instance(FakeFundingProviderAdapter::class, $adapter);
    app()->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    app()->forgetInstance(FundingProviderAdapterRegistry::class);

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);
});

it('runs payment voucher collection scenario with a provider generated payment QR', function (): void {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'payment_voucher_collection',
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($payload['success'])->toBeTrue()
        ->and($payload['mode'])->toBe('payment_voucher_collection')
        ->and(data_get($payload, 'voucher.flow_type'))->toBe('collectible')
        ->and(data_get($payload, 'artifacts.pay_code_qr.format'))->toBe('png_base64')
        ->and(data_get($payload, 'artifacts.pay_code_qr.rendered'))->toStartWith('data:image/png;base64,')
        ->and(data_get($payload, 'artifacts.provider_payment_qr.mime_type'))->toBe('image/png')
        ->and(data_get($payload, 'artifacts.provider_payment_qr.provider_generated'))->toBeTrue()
        ->and(data_get($payload, 'artifacts.provider_payment_qr.embedded_amount'))->toBeTrue()
        ->and(data_get($payload, 'payment_attempt.provider_generated_qr'))->toBeTrue()
        ->and(data_get($payload, 'safety.payment_rail_qr_synthesized'))->toBeFalse();
});

it('settles an explicit value flow into the named account through the lifecycle runner', function (): void {
    enableNetbankTreasuryForTests();
    config()->set('x-change.onboarding.issuer_model', User::class);
    config()->set('x-change.lifecycle.scenarios.payment_voucher_explicit_value_flow.payment.verify', true);

    $issuer = User::query()->findOrFail(1);
    $collector = User::query()->create([
        'name' => 'Lifecycle Collector',
        'email' => 'lifecycle-collector@example.test',
        'password' => 'not-a-login-credential',
    ]);
    $collector->forceFill(['mobile' => '09175551234'])->save();
    fundTestUserWallet($collector, 0);
    fundTestUserClientFunds($issuer, 100);
    fundTestUserClientFunds($collector, 100);
    config()->set(
        'x-change.lifecycle.scenarios.payment_voucher_explicit_value_flow.execution.metadata.value_flow.destination',
        'account.mobile:09175551234.client_funds',
    );

    $adapter = app(FakeFundingProviderAdapter::class);
    $adapter->fundingVerificationResolver = static fn ($verification): ProviderFundingObservationData => new ProviderFundingObservationData(
        provider: $verification->provider,
        providerTransactionId: 'lifecycle-value-flow-payment-1',
        grossAmountMinor: $verification->expectedAmountMinor,
        feeAmountMinor: 0,
        netAmountMinor: $verification->expectedAmountMinor,
        currency: $verification->currency,
        providerStatus: 'settled',
        verificationSource: 'fake-authoritative-api',
        payloadHash: hash('sha256', 'lifecycle-value-flow-payment-1'),
        fundingAddress: 'sha256:'.hash('sha256', (string) $verification->fundingAddress),
        occurredAt: new DateTimeImmutable('2026-07-24T04:59:00+00:00'),
        settledAt: new DateTimeImmutable('2026-07-24T05:00:00+00:00'),
        metadata: ['destination_verified' => true],
    );

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'payment_voucher_explicit_value_flow',
        '--json' => true,
    ]);
    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $voucher = Voucher::query()->findOrFail(data_get($payload, 'voucher.id'));

    expect($exitCode)->toBe(0)
        ->and(data_get($payload, 'payment_attempt.status'))->toBe('settled')
        ->and(data_get($voucher->metadata, 'instructions.execution.metadata.value_flow.destination'))
        ->toBe('account.mobile:09175551234.client_funds')
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->getKey())->count())->toBe(1)
        ->and(treasuryClientFundsLedger($collector)->balanceInt)->toBe(10_100)
        ->and(treasuryClientFundsLedger($issuer)->balanceInt)->toBe(100);
});

it('does not settle when the explicit lifecycle destination has no account', function (): void {
    enableNetbankTreasuryForTests();
    config()->set('x-change.onboarding.issuer_model', User::class);
    config()->set('x-change.lifecycle.scenarios.payment_voucher_explicit_value_flow.payment.verify', true);
    config()->set(
        'x-change.lifecycle.scenarios.payment_voucher_explicit_value_flow.execution.metadata.value_flow.destination',
        'account.mobile:09175559999.client_funds',
    );

    $issuer = User::query()->findOrFail(1);
    fundTestUserClientFunds($issuer, 100);
    $adapter = app(FakeFundingProviderAdapter::class);
    $adapter->fundingVerificationResolver = static fn ($verification): ProviderFundingObservationData => new ProviderFundingObservationData(
        provider: $verification->provider,
        providerTransactionId: 'lifecycle-value-flow-unresolved-1',
        grossAmountMinor: $verification->expectedAmountMinor,
        feeAmountMinor: 0,
        netAmountMinor: $verification->expectedAmountMinor,
        currency: $verification->currency,
        providerStatus: 'settled',
        verificationSource: 'fake-authoritative-api',
        payloadHash: hash('sha256', 'lifecycle-value-flow-unresolved-1'),
        fundingAddress: 'sha256:'.hash('sha256', (string) $verification->fundingAddress),
        occurredAt: new DateTimeImmutable('2026-07-24T04:59:00+00:00'),
        settledAt: new DateTimeImmutable('2026-07-24T05:00:00+00:00'),
        metadata: ['destination_verified' => true],
    );

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'payment_voucher_explicit_value_flow',
        '--json' => true,
    ]);
    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(1)
        ->and(data_get($payload, 'success'))->toBeFalse()
        ->and(VoucherCollection::query()->count())->toBe(0)
        ->and(treasuryClientFundsLedger($issuer)->balanceInt)->toBe(100);
});
