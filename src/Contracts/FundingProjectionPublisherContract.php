<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface FundingProjectionPublisherContract
{
    public function publish(
        string $ownerType,
        string $ownerId,
        string $reference,
        string $occurredAt,
    ): void;
}
