<?php

declare(strict_types=1);

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

it('builds a NetBank bank-account readiness descriptor', function () {
    $descriptor = app(BuildProvisioningFlowDescriptor::class)
        ->handle('netbank', ProviderProvisioningMode::BankAccountLink->value);

    expect($descriptor->provider)->toBe('netbank')
        ->and($descriptor->topology)->toBe('ledger_pooled')
        ->and($descriptor->title)->toBe('Add payout destination')
        ->and($descriptor->steps)->toContain('bank_account', 'consent')
        ->and($descriptor->fields)->toContain('bank_code', 'account_number');
});
