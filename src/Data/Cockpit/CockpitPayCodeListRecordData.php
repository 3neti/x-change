<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitPayCodeListRecordData extends Data
{
    public function __construct(
        public readonly string $code,
        public readonly string $template,
        public readonly string|int|float|null $amount,
        public readonly ?string $currency,
        public readonly string $status,
        public readonly string $display_status,
        public readonly string $owner,
        public readonly ?string $last_activity,
    ) {}
}
