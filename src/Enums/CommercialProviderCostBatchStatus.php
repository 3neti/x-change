<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum CommercialProviderCostBatchStatus: string
{
    case ReviewRequired = 'review_required';
    case Settled = 'settled';
}
