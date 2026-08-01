<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

final class CockpitSystemReadinessAccess
{
    public function isVisible(): bool
    {
        return (bool) config(
            'x-change.cockpit.system_readiness.visible',
            false,
        );
    }
}
