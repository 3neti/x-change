<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Contracts\Auth\Authenticatable;

final readonly class VoucherClaimantReference
{
    public function for(?Authenticatable $claimant): ?string
    {
        if ($claimant === null) {
            return null;
        }

        return hash(
            'sha256',
            $claimant::class.'|'.(string) $claimant->getAuthIdentifier(),
        );
    }
}
