<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Contracts\CommercialPrincipalResolverContract;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Models\CommercialPrincipal;

final readonly class ConfiguredCommercialPrincipalResolver implements CommercialPrincipalResolverContract
{
    public function resolve(): CommercialPrincipal
    {
        $reference = trim((string) config('x-change.commercial.principal.reference'));

        if ($reference === '') {
            throw new TreasuryConfigurationException('The commissioned commercial principal reference is missing.');
        }

        $principals = CommercialPrincipal::query()
            ->where('reference', $reference)
            ->where('active', true)
            ->limit(2)
            ->get();

        if ($principals->count() !== 1) {
            throw new TreasuryConfigurationException(
                'Public issuance requires exactly one active commissioned commercial principal.',
            );
        }

        return $principals->firstOrFail();
    }
}
