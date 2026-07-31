<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum CommissioningState: string
{
    case ConfigurationRequired = 'configuration_required';
    case ReadyToInstall = 'ready_to_install';
    case InstallationIncomplete = 'installation_incomplete';
    case Operational = 'operational';
}
