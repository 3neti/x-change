<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use RuntimeException;

class ProviderProvisioningRequired extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $provisioning
     */
    public function __construct(
        string $message,
        public readonly array $provisioning = [],
    ) {
        parent::__construct($message);
    }
}
