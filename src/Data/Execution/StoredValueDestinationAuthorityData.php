<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Execution;

use Spatie\LaravelData\Data;

final class StoredValueDestinationAuthorityData extends Data
{
    public function __construct(
        public readonly string $counterpartyPositionReference,
        public readonly string $authorityReference,
        public readonly string $principalReference,
    ) {}
}
