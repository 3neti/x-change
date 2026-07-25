<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use Throwable;

final readonly class FederatedTreasuryPrincipalReferenceResolver implements TreasuryPrincipalReferenceResolverContract
{
    public function __construct(
        private SystemUserResolverContract $systemUsers,
        private DefaultTreasuryPrincipalReferenceResolver $accounts,
    ) {}

    public function resolve(Model $principal): string
    {
        try {
            $system = $this->systemUsers->resolve();
        } catch (Throwable) {
            return $this->accounts->resolve($principal);
        }

        if (
            $system instanceof Model
            && $system->getMorphClass() === $principal->getMorphClass()
            && (string) $system->getKey() === (string) $principal->getKey()
        ) {
            $reference = trim((string) config(
                'x-change.treasury.principal_reference',
            ));

            if ($reference === '') {
                throw new TreasuryConfigurationException(
                    'Treasury configuration [principal_reference] is required for the system principal.',
                );
            }

            return $reference;
        }

        return $this->accounts->resolve($principal);
    }
}
