<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

use Spatie\LaravelData\Data;

class ClaimExperienceDiagnosticsData extends Data
{
    public function __construct(
        public bool $duplicate_splash_prevented = false,
        public ?string $redirect_owner = null,
        public array $consumed = [],
        public array $warnings = [],
    ) {}
}
