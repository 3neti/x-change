<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum ClaimEvidenceStatus: string
{
    case Captured = 'captured';
    case Verified = 'verified';
    case Failed = 'failed';
    case Missing = 'missing';
    case NotRetained = 'not_retained';
}
