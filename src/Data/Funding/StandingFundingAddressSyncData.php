<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

final class StandingFundingAddressSyncData
{
    public function __construct(
        public readonly int $observed,
        public readonly int $settled,
        public readonly int $awaitingApproval,
        public readonly int $suspense,
        public readonly int $applied = 0,
    ) {}
}
