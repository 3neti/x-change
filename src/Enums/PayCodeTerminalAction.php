<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum PayCodeTerminalAction: string
{
    case Expire = 'expire';
    case Cancel = 'cancel';
}
