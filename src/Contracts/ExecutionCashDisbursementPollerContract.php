<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface ExecutionCashDisbursementPollerContract
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function poll(string $code, array $options = []): array;
}
