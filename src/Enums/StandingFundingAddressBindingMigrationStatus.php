<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum StandingFundingAddressBindingMigrationStatus: string
{
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case Activated = 'activated';
    case ReviewRequired = 'review_required';
}
