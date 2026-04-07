<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use Spatie\LaravelData\Data;

class WalletData extends Data
{
    public function __construct(
        public mixed $id,
        public ?string $slug = null,
        public ?string $name = null,
        public int|float|string|null $balance = null,
    ) {}
}
