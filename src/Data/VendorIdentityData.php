<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use Spatie\LaravelData\Data;

class VendorIdentityData extends Data
{
    public function __construct(
        public string $canonicalAlias,
        public ?string $vendorId = null,
        public ?string $displayName = null,
        public array $aliases = [],
        public array $meta = [],
    ) {}
}
