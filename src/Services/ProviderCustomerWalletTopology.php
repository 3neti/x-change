<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\XChangeProviderTopologyContract;

class ProviderCustomerWalletTopology implements XChangeProviderTopologyContract
{
    public function key(): string
    {
        return 'provider_customer_wallet';
    }

    public function provisionIssuer(mixed $issuer, array $context = []): array
    {
        return [
            'topology' => $this->key(),
            'issuer_id' => $this->issuerId($issuer),
            'provider' => (string) data_get($context, 'provider', 'paynamics'),
            'customer_wallet_id' => $this->customerWalletId($issuer, $context),
            'status' => $this->customerWalletId($issuer, $context) ? 'ready' : 'pending_provider_customer_wallet',
        ];
    }

    public function resolveFundingSource(mixed $issuer, array $context = []): array
    {
        return [
            'topology' => $this->key(),
            'issuer_id' => $this->issuerId($issuer),
            'provider' => (string) data_get($context, 'provider', 'paynamics'),
            'customer_wallet_id' => $this->customerWalletId($issuer, $context),
            'source' => 'provider_customer_wallet',
        ];
    }

    public function requiresProviderCredentialsPerUser(): bool
    {
        return true;
    }

    public function usesLocalLedgerAsSourceOfTruth(): bool
    {
        return false;
    }

    protected function issuerId(mixed $issuer): mixed
    {
        if (is_object($issuer) && method_exists($issuer, 'getKey')) {
            return $issuer->getKey();
        }

        return is_object($issuer) ? ($issuer->id ?? null) : data_get($issuer, 'id');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function customerWalletId(mixed $issuer, array $context): ?string
    {
        $value = data_get($context, 'provider_customer_wallet_id')
            ?? data_get($context, 'customer_wallet_id')
            ?? data_get($context, 'paynamics.customer_wallet_id')
            ?? data_get($context, 'paynamics.wallet_id')
            ?? data_get($context, 'constellation.wallet_id')
            ?? data_get($issuer, 'provider_customer_wallet_id')
            ?? data_get($issuer, 'customer_wallet_id')
            ?? data_get($issuer, 'paynamics_customer_wallet_id')
            ?? data_get($issuer, 'constellation_wallet_id');

        return is_scalar($value) && trim((string) $value) !== ''
            ? trim((string) $value)
            : null;
    }
}
