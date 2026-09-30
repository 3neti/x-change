<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use RuntimeException;

final class FundingEvidenceAlreadyClaimed extends RuntimeException
{
    public static function forAnotherIntent(): self
    {
        return new self('The provider transaction is already claimed by another Funding Intent.');
    }
}
