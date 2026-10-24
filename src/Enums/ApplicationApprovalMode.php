<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum ApplicationApprovalMode: string
{
    case Direct = 'direct';
    case MakerChecker = 'maker_checker';
}
