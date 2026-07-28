<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

final readonly class RiderStampRecipientData
{
    public function __construct(
        public string $eyebrow,
        public string $label,
        public bool $visible,
    ) {}
}
