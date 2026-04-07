<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use Spatie\LaravelData\Data;

class IssuerData extends Data
{
    public function __construct(
        public mixed $id,
        public ?string $name = null,
        public ?string $email = null,
        public ?string $mobile = null,
        public ?string $country = null,
    ) {}
}
