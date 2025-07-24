<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class CostBreakdownData extends Data
{
    public function __construct(
        public array $breakdown, // ['cash' => 500, 'some_instruction' => 10, ...]
        public float $total
    ) {}
}
