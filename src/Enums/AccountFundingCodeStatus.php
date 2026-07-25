<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum AccountFundingCodeStatus: string
{
    case Issued = 'issued';
    case Claimed = 'claimed';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
