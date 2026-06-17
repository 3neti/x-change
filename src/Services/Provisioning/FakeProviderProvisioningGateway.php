<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Provisioning;

use Illuminate\Support\Str;
use LBHurtado\XChange\Contracts\ProviderProvisioningGatewayContract;
use LBHurtado\XChange\Enums\ProviderProvisioningMode;

class FakeProviderProvisioningGateway implements ProviderProvisioningGatewayContract
{
    public function supports(string $provider, string $mode): bool
    {
        return in_array(strtolower($provider), ['manual', 'netbank', 'paynamics'], true)
            && ProviderProvisioningMode::tryFrom($mode) !== null;
    }

    public function provision(mixed $owner, array $payload): array
    {
        $provider = strtolower((string) data_get($payload, 'provider', 'manual'));
        $mode = (string) data_get($payload, 'mode', ProviderProvisioningMode::LedgerWallet->value);
        $topology = (string) data_get($payload, 'topology', $this->topologyForProvider($provider));
        $status = (string) data_get($payload, 'status', 'ready');

        return [
            'provider' => $provider,
            'topology' => $topology,
            'purpose' => data_get($payload, 'purpose'),
            'mode' => $mode,
            'status' => $status,
            'provider_account_id' => $provider === 'paynamics' ? 'CNSTACCTFAKE01' : null,
            'provider_wallet_id' => $provider === 'paynamics' ? 'CNSTWLLTFAKE01' : null,
            'provider_bank_account_id' => $mode === ProviderProvisioningMode::BankAccountLink->value ? 'BANKFAKE01' : null,
            'external_uid' => 'xchange-'.Str::lower(Str::random(10)),
            'verification_status' => $status === 'ready' ? 'APPROVED' : 'PENDING',
            'identity_level' => $provider === 'paynamics' ? '1' : null,
            'capabilities' => [
                'issue_pay_code' => $status === 'ready',
                'redeem_pay_code' => $status === 'ready',
            ],
            'metadata' => [
                'fake' => true,
                'bank_code' => data_get($payload, 'bank_code'),
                'account_number_masked' => data_get($payload, 'account_number_masked'),
            ],
        ];
    }

    public function refresh(mixed $link): array
    {
        return [
            'status' => data_get($link, 'status', 'ready'),
            'refreshed' => true,
        ];
    }

    private function topologyForProvider(string $provider): string
    {
        return match ($provider) {
            'paynamics' => 'provider_customer_wallet',
            'netbank' => 'ledger_pooled',
            default => 'manual',
        };
    }
}
