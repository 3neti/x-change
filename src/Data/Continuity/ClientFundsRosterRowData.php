<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Continuity;

final readonly class ClientFundsRosterRowData
{
    public function __construct(
        public string $name,
        public string $mobile,
        public int $amountMinor,
    ) {}
}
