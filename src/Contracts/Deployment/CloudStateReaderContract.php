<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts\Deployment;

interface CloudStateReaderContract
{
    /** @return array<string, mixed> */
    public function read(string $application, string $environment): array;
}
