<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Data\ProvisioningFlowDescriptorData;
use LBHurtado\XChange\Enums\ProviderProvisioningMode;

class BuildProvisioningFlowDescriptor
{
    public function handle(string $provider, string $mode, ?string $topology = null): ProvisioningFlowDescriptorData
    {
        $provider = strtolower($provider);
        $topology ??= match ($provider) {
            'paynamics' => 'provider_customer_wallet',
            'netbank' => 'ledger_pooled',
            default => 'manual',
        };

        return match ("{$provider}:{$mode}") {
            'paynamics:'.ProviderProvisioningMode::WalletCreate->value,
            'paynamics:'.ProviderProvisioningMode::WalletResolve->value => new ProvisioningFlowDescriptorData(
                provider: $provider,
                topology: $topology,
                mode: $mode,
                title: 'Create your Paynamics wallet',
                description: 'Complete wallet setup so Pay Codes can be issued and paid out.',
                steps: ['profile', 'wallet', 'kyc', 'ready'],
                fields: ['mobile', 'name', 'email', 'address', 'source_of_funds'],
                actions: ['continue', 'open_capture_link'],
            ),

            'paynamics:'.ProviderProvisioningMode::BankAccountLink->value => new ProvisioningFlowDescriptorData(
                provider: $provider,
                topology: $topology,
                mode: $mode,
                title: 'Add your payout bank account',
                description: 'Bind a bank account to your Paynamics wallet for payouts.',
                steps: ['bank_account', 'consent', 'provider_bind', 'ready'],
                fields: ['bank_code', 'account_number', 'account_name', 'consent'],
                actions: ['continue'],
            ),

            'netbank:'.ProviderProvisioningMode::BankAccountLink->value => new ProvisioningFlowDescriptorData(
                provider: $provider,
                topology: $topology,
                mode: $mode,
                title: 'Add payout destination',
                description: 'Add a payout destination for NetBank ledger-backed disbursement.',
                steps: ['bank_account', 'consent', 'ready'],
                fields: ['bank_code', 'account_number', 'account_name', 'consent'],
                actions: ['continue'],
            ),

            default => new ProvisioningFlowDescriptorData(
                provider: $provider,
                topology: $topology,
                mode: $mode,
                title: 'Complete account setup',
                steps: ['profile', 'consent', 'ready'],
                fields: ['mobile', 'name'],
                actions: ['continue'],
            ),
        };
    }
}
