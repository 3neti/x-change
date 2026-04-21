<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface RedemptionCompletionServiceContract
{
    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>|object
     */
    public function complete(string $code, array $payload): mixed;

    /**
     * @return array<string,mixed>|object|null
     */
    public function status(string $code): mixed;
}
