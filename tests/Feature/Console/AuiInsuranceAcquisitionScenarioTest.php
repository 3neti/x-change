<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function (): void {
    config()->set('app.url', 'https://x-change-sandbox.test');
    config()->set('x-change.lifecycle.defaults.user_model', User::class);
    config()->set('x-change.lifecycle.defaults.system_user_email', 'system@example.test');
    config()->set('x-change.lifecycle.defaults.test_user_email', 'lester@hurtado.ph');
    config()->set('x-change.lifecycle.defaults.test_user_mobile', '09173011987');
    config()->set('x-change.funding.providers.netbank.enabled', true);
    config()->set('x-change.payment.attempts.hash_key', 'aui-scenario-test-key');
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

it('issues an AUI insurance acquisition settlement Pay Code and reports claim and pay checkpoints', function (): void {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'campaign_aui_insurance_acquisition',
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($payload['success'])->toBeTrue()
        ->and($payload['mode'])->toBe('aui_insurance_acquisition')
        ->and(data_get($payload, 'campaign.public_endpoint_implemented'))->toBeFalse()
        ->and(data_get($payload, 'voucher.code'))->toBeString()->toStartWith('AUI')
        ->and(data_get($payload, 'voucher.voucher_type'))->toBe('settlement')
        ->and(data_get($payload, 'voucher.flow_type'))->toBe('settlement')
        ->and(data_get($payload, 'voucher.amount'))->toBe(0)
        ->and(data_get($payload, 'voucher.target_amount'))->toBe(100)
        ->and(data_get($payload, 'urls.claim'))->toContain('/x/claim/'.data_get($payload, 'voucher.code'))
        ->and(data_get($payload, 'urls.pay'))->toContain('/x/pay/'.data_get($payload, 'voucher.code'))
        ->and(data_get($payload, 'applicant.mobile'))->toBe('09175180722')
        ->and(data_get($payload, 'requirements.form_flow_fields'))->toContain('name', 'mobile', 'email', 'address', 'birth_date', 'otp')
        ->and(data_get($payload, 'requirements.aui_domain_fields'))->toContain('vehicle_registration', 'plate_number', 'license_number')
        ->and(data_get($payload, 'safety.provider_payment_attempt_created'))->toBeFalse()
        ->and(data_get($payload, 'safety.settlement_first'))->toBeTrue()
        ->and(data_get($payload, 'product_gaps_deferred'))->toContain('post-claim Continue to payment CTA');

    $voucher = Voucher::query()->where('code', data_get($payload, 'voucher.code'))->firstOrFail();

    expect(data_get($voucher->metadata, 'instructions.metadata.custom.aui.applicant.mobile'))->toBe('09175180722')
        ->and(data_get($voucher->metadata, 'instructions.metadata.custom.settlement_preferred'))->toBeTrue();
});
