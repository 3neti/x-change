<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;

final readonly class DefaultTreasuryPrincipalReferenceResolver implements TreasuryPrincipalReferenceResolverContract
{
    public function resolve(Model $principal): string
    {
        if (! $principal->exists || $principal->getKey() === null) {
            throw new TreasuryConfigurationException(
                'A persisted Account owner is required for Treasury provisioning.',
            );
        }

        $identity = implode('|', [
            $principal->getMorphClass(),
            (string) $principal->getKey(),
        ]);

        return 'principal:account:'.substr(hash('sha256', $identity), 0, 40);
    }
}
