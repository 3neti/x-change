<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum PartnerApiClientStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
}
