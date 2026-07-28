<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

final readonly class ClaimShareCardData
{
    public function __construct(
        public string $contents,
        public string $etag,
        public bool $immutable = false,
    ) {}
}
