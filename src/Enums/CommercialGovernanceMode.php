<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum CommercialGovernanceMode: string
{
    case BootstrapImmutable = 'bootstrap_immutable';
    case MakerCheckerFromStart = 'maker_checker_from_start';
}
