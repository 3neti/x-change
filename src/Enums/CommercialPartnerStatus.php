<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum CommercialPartnerStatus: string
{
    case Draft = 'draft';
    case AwaitingApproval = 'awaiting_approval';
    case Active = 'active';
    case Suspended = 'suspended';
    case Retired = 'retired';
}
