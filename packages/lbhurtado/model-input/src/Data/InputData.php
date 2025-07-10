<?php

namespace LBHurtado\ModelInput\Data;

use Spatie\LaravelData\Data;

class InputData extends Data
{
    public function __construct(
        public string $name,
        public string $value,
    ) {}
}
