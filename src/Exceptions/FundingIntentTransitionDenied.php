<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use LBHurtado\XChange\Enums\FundingIntentStatus;
use RuntimeException;

class FundingIntentTransitionDenied extends RuntimeException
{
    public static function from(FundingIntentStatus $current, FundingIntentStatus $target): self
    {
        return new self("Funding Intent cannot transition from {$current->value} to {$target->value}.");
    }
}
