<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use RuntimeException;

final class IncompatibleProviderFundingEvidence extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $classification,
    ) {
        parent::__construct($message);
    }

    public static function forField(string $transactionKey, string $field): self
    {
        return new self(
            sprintf(
                'Provider funding evidence [%s] has incompatible [%s].',
                substr($transactionKey, 0, 12),
                $field,
            ),
            'field:'.$field,
        );
    }

    public static function forStatusTransition(
        string $transactionKey,
        string $from,
        string $to,
    ): self {
        return new self(
            sprintf(
                'Provider funding evidence [%s] has incompatible status transition [%s -> %s].',
                substr($transactionKey, 0, 12),
                $from,
                $to,
            ),
            'status_transition:'.$from.':'.$to,
        );
    }
}
