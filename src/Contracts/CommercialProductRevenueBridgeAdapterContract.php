<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface CommercialProductRevenueBridgeAdapterContract
{
    /** @param array<string, mixed> $plan */
    public function post(array $plan): array;

    /** @param array<string, mixed> $plan */
    public function reverse(array $plan): array;
}
