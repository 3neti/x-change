<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use RuntimeException;

final class IncompleteClaimEvidence extends RuntimeException
{
    /** @param list<string> $missingRequirements */
    public function __construct(public readonly array $missingRequirements)
    {
        parent::__construct(sprintf(
            'Required claim evidence is incomplete: %s.',
            implode(', ', $missingRequirements),
        ));
    }
}
