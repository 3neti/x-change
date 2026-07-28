<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

final readonly class RiderStampCopyData
{
    public function __construct(
        public string $source,
        public string $title,
        public string $description,
        public bool $visible,
    ) {}
}
