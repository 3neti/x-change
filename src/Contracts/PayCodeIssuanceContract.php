<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface PayCodeIssuanceContract
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function issue(mixed $issuer, array $input): array;
}
