<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use LBHurtado\EmiCore\Models\BankAccount;
use LBHurtado\EmiCore\Models\ProviderAccount;
use LBHurtado\EmiCore\Models\Wallet;
use LBHurtado\XChange\Enums\ProviderProvisioningMode;
use LBHurtado\XChange\Services\Provisioning\PaynamicsProviderProvisioningGateway;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function () {
    config()->set('constellation.base_url', 'https://asterism.payserv.net/v1');
    config()->set('constellation.username', 'test-user');
    config()->set('constellation.password', 'test-password');
    config()->set('constellation.merchant_key', 'test-merchant-key');
    config()->set('constellation.notification_url', 'https://example.com/webhook');
    config()->set('constellation.company.email', 'company@example.test');
    config()->set('constellation.company.mobile_no', '639170000000');
    config()->set('constellation.company.business_address', 'Pasig City');
    config()->set('constellation.company.business_zip', '1605');
    config()->set('constellation.company.business_city', 'Pasig City');
    config()->set('constellation.company.business_state', 'Metro Manila');
    config()->set('constellation.company.business_country', 'PH');
    config()->set('constellation.bank_map.GXCHPHM2XXX', '67cec0d9e5a2ea23098c3730');
});

it('provisions a fake Paynamics wallet and promotes ready overrides into emi-core records', function () {
    $owner = User::query()->create([
        'name' => 'Paynamics Ready Owner',
        'email' => 'paynamics-ready@example.test',
        'mobile' => '09171234567',
        'password' => 'password',
    ]);

    $gateway = app(PaynamicsProviderProvisioningGateway::class);

    $result = $gateway->provision($owner, [
        'provider' => 'paynamics',
        'mode' => ProviderProvisioningMode::WalletCreate->value,
        'purpose' => 'IssuePayCode',
        'status' => 'ready',
    ]);

    $wallet = Wallet::query()->findOrFail(data_get($result, 'emi_wallet_id'));
    $providerAccount = ProviderAccount::query()->findOrFail(data_get($result, 'emi_provider_account_id'));

    expect($result)->toMatchArray([
        'provider' => 'paynamics',
        'mode' => ProviderProvisioningMode::WalletCreate->value,
        'status' => 'ready',
        'verification_status' => 'APPROVED',
        'identity_level' => '1',
    ])
        ->and($providerAccount->provider_code->value)->toBe('paynamics_constellation')
        ->and($wallet->provider_wallet_id)->toBe('CNSTWLLTFAKE02')
        ->and($wallet->external_uid)->toBe('xchange-paynamics-'.$owner->getKey())
        ->and($wallet->capture_link)->toBe('https://capture.kyc.idfy.com/fake')
        ->and(data_get($result, 'metadata.fake'))->toBeTrue();
});

it('maps live-mode Paynamics wallet and bank-account responses into emi-core records', function () {
    config()->set('x-change.provider_runtime.providers.paynamics.live_requests_enabled', true);

    Http::fake([
        '*/integration/corp_wallet/customer_wallet/add' => Http::response([
            'success' => true,
            'data' => [
                'wallet_id' => 'CNSTWLLTABCDEF',
                'account_id' => 'CNSTCSTMR12345',
                'account_no' => '071127093068',
                'wallet_type' => 'Personal',
                'status' => 'Active',
                'balance' => '0.00',
                'currency' => 'PHP',
                'compliance_level' => '0',
                'required_compliance' => 'level 1',
                'verification_status' => 'PENDING',
                'capture_link' => 'https://capture.kyc.idfy.com/captures?t=abc123',
                'notification_url' => 'https://example.com/webhook',
            ],
        ]),
        '*/integration/corp_wallet/kyc_request' => Http::response([
            'success' => true,
            'data' => [
                'response_code' => 'GR169',
                'capture_link' => 'https://capture.kyc.idfy.com/kyc?t=abc123',
                'response_message' => 'Wallet KYC Request Pending',
            ],
        ]),
        '*/integration/corp_wallet/bank_account/create' => Http::response([
            'success' => true,
            'data' => [
                'bank_account_id' => 'FAKEBA001',
            ],
        ]),
    ]);

    $owner = User::query()->create([
        'name' => 'Live Paynamics Owner',
        'email' => 'paynamics-live@example.test',
        'mobile' => '09181234567',
        'password' => 'password',
    ]);

    $gateway = app(PaynamicsProviderProvisioningGateway::class);

    $result = $gateway->provision($owner, [
        'provider' => 'paynamics',
        'mode' => ProviderProvisioningMode::BankAccountLink->value,
        'purpose' => 'BankOnboardingRequired',
        'account_number' => '123456789012',
        'bank_code' => 'GXCHPHM2XXX',
        'bank_name' => 'GCash',
        'notification_url' => 'https://example.com/webhook',
    ]);

    $wallet = Wallet::query()->findOrFail(data_get($result, 'emi_wallet_id'));
    $bankAccount = BankAccount::query()->findOrFail(data_get($result, 'emi_bank_account_id'));

    expect($result)->toMatchArray([
        'provider' => 'paynamics',
        'mode' => ProviderProvisioningMode::BankAccountLink->value,
        'status' => 'pending',
        'provider_wallet_id' => 'CNSTWLLTABCDEF',
        'provider_account_id' => 'CNSTCSTMR12345',
        'provider_bank_account_id' => 'FAKEBA001',
        'verification_status' => 'PENDING',
    ])
        ->and($wallet->external_uid)->toBe('xchange-paynamics-'.$owner->getKey())
        ->and($wallet->provider_account_id_value)->toBe('CNSTCSTMR12345')
        ->and($wallet->capture_link)->toBe('https://capture.kyc.idfy.com/kyc?t=abc123')
        ->and($bankAccount->bank_code)->toBe('GXCHPHM2XXX')
        ->and($bankAccount->account_number_masked)->toBe('********9012')
        ->and($bankAccount->is_registered)->toBeTrue()
        ->and(data_get($result, 'metadata.fake'))->toBeFalse();

    Http::assertSentCount(3);
});
