<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Contracts\ProviderProvisioningManagerContract;
use LBHurtado\XChange\Enums\ProviderProvisioningMode;
use LBHurtado\XChange\Models\ProviderAccountLink;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function () {
    config()->set('constellation.base_url', 'https://asterism.payserv.net/v1');
    config()->set('constellation.username', 'test-user');
    config()->set('constellation.password', 'test-password');
    config()->set('constellation.merchant_key', 'test-merchant-key');
    config()->set('constellation.notification_url', 'https://example.com/webhook');
    config()->set('constellation.company.email', 'company@example.test');
    config()->set('constellation.company.mobile_no', '639170000000');
});

it('routes NetBank provisioning through the configured gateway and persists a ready link', function () {
    $owner = User::query()->create([
        'name' => 'Netbank Gateway Owner',
        'email' => 'netbank-gateway@example.test',
        'mobile' => '639171234568',
        'password' => 'password',
    ]);

    $result = app(ProviderProvisioningManagerContract::class)->startOrResume($owner, [
        'provider' => 'netbank',
        'mode' => ProviderProvisioningMode::BankAccountLink->value,
        'purpose' => 'BankOnboardingRequired',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******1987',
    ]);

    expect($result)->toMatchArray([
        'provider' => 'netbank',
        'mode' => ProviderProvisioningMode::BankAccountLink->value,
        'status' => 'ready',
        'ready' => true,
    ]);

    $link = ProviderAccountLink::query()->findOrFail(data_get($result, 'link_id'));

    expect($link)->toBeInstanceOf(ProviderAccountLink::class)
        ->and($link->provider)->toBe('netbank')
        ->and($link->mode)->toBe(ProviderProvisioningMode::BankAccountLink->value)
        ->and($link->status)->toBe('ready')
        ->and($link->provider_bank_account_id)->toBe('NETBANK-GXCHPHM2XXX-XXXXXXX1987')
        ->and(data_get($link->metadata, 'bank_code'))->toBe('GXCHPHM2XXX');
});

it('routes Paynamics provisioning through the configured gateway and persists a pending wallet link', function () {
    config()->set('x-change.provider_runtime.providers.paynamics.live_requests_enabled', true);

    Http::fake([
        '*/integration/corp_wallet/customer_wallet/add' => Http::response([
            'success' => true,
            'data' => [
                'wallet_id' => 'CNSTWLLTMANAGER01',
                'account_id' => 'CNSTCSTMGR0001',
                'account_no' => '071127093068',
                'status' => 'Active',
                'balance' => '0.00',
                'currency' => 'PHP',
                'compliance_level' => '0',
                'verification_status' => 'PENDING',
                'capture_link' => 'https://capture.kyc.idfy.com/captures?t=manager',
                'notification_url' => 'https://example.com/webhook',
            ],
        ]),
        '*/integration/corp_wallet/kyc_request' => Http::response([
            'success' => true,
            'data' => [
                'response_code' => 'GR169',
                'capture_link' => 'https://capture.kyc.idfy.com/kyc?t=manager',
                'response_message' => 'Wallet KYC Request Pending',
            ],
        ]),
    ]);

    $owner = User::query()->create([
        'name' => 'Paynamics Gateway Owner',
        'email' => 'paynamics-gateway@example.test',
        'mobile' => '639171234560',
        'password' => 'password',
    ]);

    $result = app(ProviderProvisioningManagerContract::class)->startOrResume($owner, [
        'provider' => 'paynamics',
        'mode' => ProviderProvisioningMode::WalletCreate->value,
        'purpose' => 'IssuePayCode',
        'notification_url' => 'https://example.com/webhook',
    ]);

    expect($result)->toMatchArray([
        'provider' => 'paynamics',
        'mode' => ProviderProvisioningMode::WalletCreate->value,
        'status' => 'pending',
        'ready' => false,
    ]);

    $link = ProviderAccountLink::query()->findOrFail(data_get($result, 'link_id'));

    expect($link)->toBeInstanceOf(ProviderAccountLink::class)
        ->and($link->provider)->toBe('paynamics')
        ->and($link->provider_wallet_id)->toBe('CNSTWLLTMANAGER01')
        ->and($link->provider_account_id)->toBe('CNSTCSTMGR0001')
        ->and($link->status)->toBe('pending')
        ->and($link->emi_wallet_id)->not->toBeNull();
});
