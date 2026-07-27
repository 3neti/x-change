<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Contracts\CockpitTreasuryAccessContract;
use Throwable;

final readonly class SystemPrincipalCockpitTreasuryAccess implements CockpitTreasuryAccessContract
{
    public function __construct(
        private SystemUserResolverContract $systemUsers,
    ) {}

    public function canViewTreasuryControls(Authenticatable $actor): bool
    {
        return $this->isSystemPrincipal($actor);
    }

    public function canRefreshProviderLiquidity(Authenticatable $actor): bool
    {
        return $this->isSystemPrincipal($actor);
    }

    public function canManageTreasuryReconciliation(Authenticatable $actor): bool
    {
        return $this->isSystemPrincipal($actor);
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeProviderLiquidityRefresh(Authenticatable $actor): void
    {
        if (! $this->canRefreshProviderLiquidity($actor)) {
            throw new AuthorizationException(
                'Provider liquidity controls are restricted to System Treasury.',
            );
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeTreasuryReconciliation(Authenticatable $actor): void
    {
        if (! $this->canManageTreasuryReconciliation($actor)) {
            throw new AuthorizationException(
                'Treasury reconciliation controls are restricted to System Treasury.',
            );
        }
    }

    private function isSystemPrincipal(Authenticatable $actor): bool
    {
        try {
            $system = $this->systemUsers->resolve();
        } catch (Throwable) {
            return false;
        }

        return $actor instanceof Model
            && $system instanceof Model
            && $system->getMorphClass() === $actor->getMorphClass()
            && (string) $system->getKey() === (string) $actor->getKey();
    }
}
