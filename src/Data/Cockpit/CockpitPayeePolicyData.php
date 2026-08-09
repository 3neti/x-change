<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use LBHurtado\XChange\Enums\CockpitPayeeKind;
use Spatie\LaravelData\Data;

final class CockpitPayeePolicyData extends Data
{
    public function __construct(
        public CockpitPayeeKind $kind,
        public ?string $normalizedValue,
        public string $displayValue,
        public bool $explicitSecret,
        public bool $issuable,
        public string $message,
    ) {}
}
