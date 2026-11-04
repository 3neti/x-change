<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\ProviderProvisioningPresentation;
use LBHurtado\XChange\Enums\ProviderProvisioningMode;
use LBHurtado\XChange\Services\BuildProvisioningFlowDescriptor;

it('builds a Paynamics wallet provisioning descriptor', function () {
    $descriptor = app(BuildProvisioningFlowDescriptor::class)
        ->handle('paynamics', ProviderProvisioningMode::WalletCreate->value);

    expect($descriptor->provider)->toBe('paynamics')
        ->and($descriptor->topology)->toBe('provider_customer_wallet')
        ->and($descriptor->title)->toBe('Create your Paynamics wallet')
        ->and($descriptor->steps)->toContain('wallet', 'kyc')
        ->and($descriptor->fields)->toContain('mobile', 'source_of_funds');
});

it('uses the presentation declared by another provider', function (): void {
    app()->instance('test.other-presentation', new class implements ProviderProvisioningPresentation
    {
        public function providerCode(): string
        {
            return 'other';
        }

        public function defaultProvisioningTopology(): string
        {
            return 'ledger_pooled';
        }

        public function provisioningPresentation(string $mode): ?array
        {
            return ['title' => 'Other provider setup', 'steps' => ['verify'], 'fields' => ['account'], 'actions' => ['continue']];
        }
    });
    app()->tag('test.other-presentation', 'emi.provider-provisioning-profiles');

    $descriptor = app(BuildProvisioningFlowDescriptor::class)->handle('other', 'account_setup');

    expect($descriptor->title)->toBe('Other provider setup')
        ->and($descriptor->topology)->toBe('ledger_pooled')
        ->and($descriptor->steps)->toBe(['verify']);
});

it('builds a NetBank bank-account readiness descriptor', function () {
    $descriptor = app(BuildProvisioningFlowDescriptor::class)
        ->handle('netbank', ProviderProvisioningMode::BankAccountLink->value);

    expect($descriptor->provider)->toBe('netbank')
        ->and($descriptor->topology)->toBe('ledger_pooled')
        ->and($descriptor->title)->toBe('Add payout destination')
        ->and($descriptor->steps)->toContain('bank_account', 'consent')
        ->and($descriptor->fields)->toContain('bank_code', 'account_number');
});
