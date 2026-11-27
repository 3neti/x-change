<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Banking;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Contracts\CockpitTreasuryAccessContract;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;

final readonly class BankAccountAdministrationAccess
{
    public function __construct(
        private CockpitTreasuryAccessContract $systemTreasury,
        private TreasuryOperatorAuthority $operators,
    ) {}

    public function canViewAll(Authenticatable $actor): bool
    {
        return $this->isSystemTreasury($actor)
            || $this->allows($actor, TreasuryOperatorCapability::ViewBankAccounts)
            || $this->canManage($actor)
            || $this->canConfigure($actor);
    }

    public function canManage(Authenticatable $actor): bool
    {
        return $this->isSystemTreasury($actor)
            || $this->allows($actor, TreasuryOperatorCapability::ManageBankAccounts);
    }

    public function canConfigure(Authenticatable $actor): bool
    {
        return $this->isSystemTreasury($actor)
            || $this->allows($actor, TreasuryOperatorCapability::ConfigureBankAccounts);
    }

    private function isSystemTreasury(Authenticatable $actor): bool
    {
        return $this->systemTreasury->canManageTreasuryReconciliation($actor);
    }

    private function allows(Authenticatable $actor, TreasuryOperatorCapability $capability): bool
    {
        return $actor instanceof Model && $this->operators->allows($actor, $capability);
    }
}
