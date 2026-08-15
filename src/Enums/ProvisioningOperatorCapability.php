<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum ProvisioningOperatorCapability: string
{
    case View = 'provisioning.view';
    case Request = 'provisioning.request';
    case Approve = 'provisioning.approve';
    case Issue = 'provisioning.issue';
    case Activate = 'provisioning.activate';
    case Revoke = 'provisioning.revoke';
}
