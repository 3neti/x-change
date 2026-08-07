<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum CommercialOfferingStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Published = 'published';
    case Retired = 'retired';
}
