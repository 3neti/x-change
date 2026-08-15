<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum PartnerApiOperatorCapability: string
{
    case ViewClients = 'partner_api.clients.view';
    case ManageSandboxClients = 'partner_api.sandbox.manage';
    case SuspendClients = 'partner_api.clients.suspend';
    case RevokeClients = 'partner_api.clients.revoke';
}
