<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use RuntimeException;

class FundingProviderUnavailable extends RuntimeException
{
    public static function forProvider(string $provider): self
    {
        return new self("Funding provider [{$provider}] is not available.");
    }
}
