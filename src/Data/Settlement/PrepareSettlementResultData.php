<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use Spatie\LaravelData\Data;

class PrepareSettlementResultData extends Data
{
    /**
     * @param  array<string, mixed>  $requirements
     * @param  array<string, mixed>  $capabilities
     * @param  array<string, mixed>  $messages
     */
    public function __construct(
        public string $voucher_code,
        public bool $can_start,
        public string $entry_route,
        public bool $requires_envelope,
        public array $requirements = [],
        public array $capabilities = [],
        public array $messages = [],
    ) {}
}
