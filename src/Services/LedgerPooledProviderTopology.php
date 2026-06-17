<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Contracts\WalletProvisioningContract;
use LBHurtado\XChange\Contracts\XChangeProviderTopologyContract;

class LedgerPooledProviderTopology implements XChangeProviderTopologyContract
{
    public function __construct(
        protected WalletProvisioningContract $walletProvisioning,
        protected WalletAccessContract $wallets,
    ) {}

    public function key(): string
    {
        return 'ledger_pooled';
    }

    public function provisionIssuer(mixed $issuer, array $context = []): mixed
    {
        return $this->walletProvisioning->open($issuer, $context);
    }

    public function resolveFundingSource(mixed $issuer, array $context = []): mixed
    {
        return $this->wallets->resolveForUser($issuer);
    }

    public function requiresProviderCredentialsPerUser(): bool
    {
        return false;
    }

    public function usesLocalLedgerAsSourceOfTruth(): bool
    {
        return true;
    }
}
