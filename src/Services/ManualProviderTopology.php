<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\XChangeProviderTopologyContract;

class ManualProviderTopology implements XChangeProviderTopologyContract
{
    public function key(): string
    {
        return 'manual';
    }

    public function provisionIssuer(mixed $issuer, array $context = []): array
    {
        return [
            'topology' => $this->key(),
            'issuer_id' => $this->issuerId($issuer),
            'status' => 'ready',
            'mode' => 'manual',
        ];
    }

    public function resolveFundingSource(mixed $issuer, array $context = []): array
    {
        return [
            'topology' => $this->key(),
            'issuer_id' => $this->issuerId($issuer),
            'source' => 'manual',
        ];
    }

    public function requiresProviderCredentialsPerUser(): bool
    {
        return false;
    }

    public function usesLocalLedgerAsSourceOfTruth(): bool
    {
        return true;
    }

    protected function issuerId(mixed $issuer): mixed
    {
        if (is_object($issuer) && method_exists($issuer, 'getKey')) {
            return $issuer->getKey();
        }

        return is_object($issuer) ? ($issuer->id ?? null) : data_get($issuer, 'id');
    }
}
