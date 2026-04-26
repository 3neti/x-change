<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\VendorIdentityData;

interface VendorRegistryContract
{
    public function normalize(?string $alias): ?string;

    public function resolve(?string $alias, array $context = []): ?VendorIdentityData;
}
