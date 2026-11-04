<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use LBHurtado\EmiCore\Contracts\ProviderSourceAccountReadiness;
use LogicException;

final class SourceAccountReadinessResolver
{
    public function for(string $provider): ?ProviderSourceAccountReadiness
    {
        $matches = [];
        foreach (app()->tagged('emi.provider-source-account-readiness') as $candidate) {
            if ($candidate instanceof ProviderSourceAccountReadiness && strtolower(trim($candidate->providerCode())) === strtolower(trim($provider))) {
                $matches[] = $candidate;
            }
        }

        if (count($matches) > 1) {
            throw new LogicException('Multiple source account readiness providers are registered.');
        }

        return $matches[0] ?? null;
    }
}
