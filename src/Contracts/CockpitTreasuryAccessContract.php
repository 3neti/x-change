<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface CockpitTreasuryAccessContract
{
    public function canViewTreasuryControls(Authenticatable $actor): bool;

    public function canRefreshProviderLiquidity(Authenticatable $actor): bool;

    public function canManageTreasuryReconciliation(Authenticatable $actor): bool;

    public function authorizeProviderLiquidityRefresh(Authenticatable $actor): void;

    public function authorizeTreasuryReconciliation(Authenticatable $actor): void;
}
