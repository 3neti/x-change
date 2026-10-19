<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface CommercialProductRevenueMovementContract
{
    /** @param array<string, mixed> $transfer */
    public function post(array $transfer): array;

    /** @param array<string, mixed> $transfer */
    public function reverse(array $transfer): array;
}
