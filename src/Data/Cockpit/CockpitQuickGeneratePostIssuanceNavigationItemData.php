<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitQuickGeneratePostIssuanceNavigationItemData extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?string $href = null,
        public readonly string $status = 'not_wired',
        public readonly bool $enabled = false,
        public readonly bool $read_only = true,
        public readonly string $reason = 'not-loaded',
    ) {}
}
