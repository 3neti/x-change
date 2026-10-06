<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum StandingFundingAddressSyncStatus: string
{
    case Idle = 'idle';
    case Queued = 'queued';
    case Running = 'running';
    case Cooldown = 'cooldown';
    case Quarantined = 'quarantined';
    case Ambiguous = 'ambiguous';
}
