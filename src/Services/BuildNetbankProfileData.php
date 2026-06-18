<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;

class BuildNetbankProfileData
{
    public function __construct(
        protected ProviderRuntimeSettingsResolverContract $settings,
        protected CheckNetbankSourceAccountReadiness $sourceAccount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        return [
            'provider' => 'netbank',
            'active' => $this->settings->provider() === 'netbank',
            'topology' => 'ledger_pooled',
            'client_alias' => $this->stringConfig('disbursement.client.alias')
                ?? $this->stringConfig('omnipay.gateways.netbank.options.clientAlias'),
            'source_account_number' => $this->stringConfig('disbursement.source.account_number')
                ?? $this->stringConfig('omnipay.gateways.netbank.options.sourceAccountNumber'),
            'sender_customer_id' => $this->stringConfig('disbursement.source.sender.customer_id')
                ?? $this->stringConfig('omnipay.gateways.netbank.options.senderCustomerId'),
            'source_account_readiness' => $this->sourceAccount->handle(),
        ];
    }

    protected function stringConfig(string $key): ?string
    {
        $value = config($key);

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
