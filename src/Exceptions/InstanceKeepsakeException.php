<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use RuntimeException;

final class InstanceKeepsakeException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }
}
