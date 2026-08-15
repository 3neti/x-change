<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum PartnerApiProductionMandateStatus: string
{
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case Activated = 'activated';
    case Rejected = 'rejected';
}
