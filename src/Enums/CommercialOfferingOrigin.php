<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum CommercialOfferingOrigin: string
{
    case InstallationBaseline = 'installation_baseline';
    case MakerCheckerRevision = 'maker_checker_revision';
}
