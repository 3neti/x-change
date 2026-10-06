<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum StandingFundingSyncRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Stale = 'stale';
    case Rejected = 'rejected';
    case Ambiguous = 'ambiguous';
}
