<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum CommercialActivationAuthority: string
{
    case CommissioningManifest = 'commissioning_manifest';
    case IndependentApproval = 'independent_approval';
}
