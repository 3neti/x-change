<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum TreasuryConnectionMode: string
{
    case Required = 'required';
    case Optional = 'optional';
    case Disabled = 'disabled';
}
