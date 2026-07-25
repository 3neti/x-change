<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

use Spatie\LaravelData\Data;

final class SystemAccountFundingPayCodeAuthorizationData extends Data
{
    public function __construct(
        public readonly int $amountMinor,
        public readonly string $connectionReference,
        public readonly bool $bearer,
        public readonly bool $commit,
        public readonly bool $productionConfirmed,
        public readonly string $idempotencyReference,
        public readonly ?string $evidenceReference = null,
        public readonly ?string $authorizationReference = null,
    ) {}
}
