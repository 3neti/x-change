<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\ProviderReadinessData;

interface ProviderReadinessGuardContract
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function evaluateIssuer(mixed $owner, ?string $provider = null, array $context = []): ProviderReadinessData;

    /**
     * @param  array<string, mixed>  $context
     */
    public function evaluateClaimant(mixed $owner, ?string $provider = null, array $context = []): ProviderReadinessData;
}
