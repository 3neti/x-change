<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use Spatie\LaravelData\Data;

class SettlementExecutionResultData extends Data
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $voucher_code,
        public string $status,
        public string $message,
        public array $meta = [],
    ) {}
}
