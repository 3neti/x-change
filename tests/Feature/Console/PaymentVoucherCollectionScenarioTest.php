<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
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
