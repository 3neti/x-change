<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

use Spatie\LaravelData\Data;

class ClaimExperienceDiagnosticsData extends Data
{
    public function __construct(
        public bool $duplicate_splash_prevented = false,
        public ?string $redirect_owner = null,
        public ?string $splash_owner = null,
        public ?string $form_flow_splash_policy = null,
        public array $consumed = [],
        public array $warnings = [],
        public ?string $form_flow_owner = null,
    ) {}
}
