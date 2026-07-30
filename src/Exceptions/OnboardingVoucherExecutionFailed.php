<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use RuntimeException;

final class OnboardingVoucherExecutionFailed extends RuntimeException
{
    public function __construct(
        public readonly string $failure,
    ) {
        parent::__construct($failure);
    }
}
