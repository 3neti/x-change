<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

final class CockpitPayCodeTimingData extends Data
{
    public function __construct(
        public readonly ?string $created_at = null,
        public readonly ?string $starts_at = null,
        public readonly ?string $expires_at = null,
        public readonly ?string $redeemed_at = null,
    ) {}
}
