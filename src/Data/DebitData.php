<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use Spatie\LaravelData\Data;

class DebitData extends Data
{
    public function __construct(
        public mixed $id = null,
        public int|float|string|null $amount = null,
    ) {}
}
