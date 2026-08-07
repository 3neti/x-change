<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum CommercialOperatorCapability: string
{
    case ManageOfferings = 'commercial.offerings.manage';
    case ApproveOfferings = 'commercial.offerings.approve';
    case ViewCommercialControls = 'commercial.controls.view';
}
