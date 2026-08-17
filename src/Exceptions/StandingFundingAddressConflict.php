<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use InvalidArgumentException;

final class StandingFundingAddressConflict extends InvalidArgumentException
{
    public static function alreadyBound(): self
    {
        return new self(
            'This QR Ph funding address is already bound to a different funding ledger. Funding is blocked until an operator reconciles the existing binding.',
        );
    }
}
