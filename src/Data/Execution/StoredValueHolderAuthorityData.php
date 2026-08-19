<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Execution;

use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

final class StoredValueHolderAuthorityData extends Data
{
    public function __construct(
        public readonly Model $holder,
        public readonly string $authorityReference,
        public readonly string $principalReference,
    ) {}
}
