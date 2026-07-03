<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface CockpitRedactorContract
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $sensitiveKeys
     * @return array<string, mixed>
     */
    public function redact(array $payload, array $sensitiveKeys = []): array;
}
