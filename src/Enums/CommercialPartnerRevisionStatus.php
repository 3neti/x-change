<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum CommercialPartnerRevisionStatus: string
{
    case Draft = 'draft';
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case Superseded = 'superseded';
}
