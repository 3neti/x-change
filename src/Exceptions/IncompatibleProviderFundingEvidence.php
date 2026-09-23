<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use RuntimeException;

final class IncompatibleProviderFundingEvidence extends RuntimeException
{
    public static function forField(string $transactionKey, string $field): self
    {
        return new self(sprintf(
            'Provider funding evidence [%s] has incompatible [%s].',
            substr($transactionKey, 0, 12),
            $field,
        ));
    }

    public static function forStatusTransition(
        string $transactionKey,
        string $from,
        string $to,
    ): self {
        return new self(sprintf(
            'Provider funding evidence [%s] has incompatible status transition [%s -> %s].',
            substr($transactionKey, 0, 12),
            $from,
            $to,
        ));
    }
}
