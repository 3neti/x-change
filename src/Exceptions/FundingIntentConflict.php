<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use RuntimeException;

class FundingIntentConflict extends RuntimeException
{
    public static function idempotency(): self
    {
        return new self('The Funding Intent idempotency key was already used for a different request.');
    }

    public static function version(int $expected, int $actual): self
    {
        return new self("Funding Intent version conflict: expected {$expected}, found {$actual}.");
    }
}
