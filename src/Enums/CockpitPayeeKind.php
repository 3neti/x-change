<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum CockpitPayeeKind: string
{
    case Open = 'open';
    case Mobile = 'mobile';
    case Email = 'email';
    case Vendor = 'vendor';
    case Secret = 'secret';
    case Invalid = 'invalid';
}
