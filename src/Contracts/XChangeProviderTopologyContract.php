<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface XChangeProviderTopologyContract
{
    public function key(): string;

    /**
     * @param  array<string, mixed>  $context
     */
    public function provisionIssuer(mixed $issuer, array $context = []): mixed;

    /**
     * @param  array<string, mixed>  $context
     */
    public function resolveFundingSource(mixed $issuer, array $context = []): mixed;

    public function requiresProviderCredentialsPerUser(): bool;

    public function usesLocalLedgerAsSourceOfTruth(): bool;
}
