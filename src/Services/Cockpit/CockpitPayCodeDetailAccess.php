<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\CockpitTreasuryAccessContract;

final readonly class CockpitPayCodeDetailAccess
{
    public function __construct(
        private CockpitTreasuryAccessContract $treasuryAccess,
    ) {}

    public function canView(Authenticatable $actor, Voucher $voucher): bool
    {
        if ($actor instanceof Model
            && $voucher->owner_type === $actor->getMorphClass()
            && (string) $voucher->owner_id === (string) $actor->getKey()) {
            return true;
        }

        return $this->treasuryAccess->canViewTreasuryControls($actor);
    }
}
