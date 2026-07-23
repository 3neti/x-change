<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use RuntimeException;

class FundingSettlementDenied extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self('Funding settlement denied: '.$reason);
    }
}
