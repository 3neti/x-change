<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use RuntimeException;

final class StandingFundingAddressBindingTimeUnavailable extends RuntimeException
{
    public static function forCutoverAddress(): self
    {
        return new self(
            'The provider occurrence time is required to resolve this funding address binding.',
        );
    }
}
