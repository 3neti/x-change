<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

use Spatie\LaravelData\Data;

class ClaimSurfaceData extends Data
{
    /**
     * @param  array<string, mixed>  $facts
     * @param  array<int, array<string, mixed>>  $components
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public readonly string $code,
        public readonly ClaimSurfaceViewerData $viewer,
        public readonly ClaimSurfaceStateData $state,
        public readonly string $visibility,
        public readonly string $headline,
        public readonly ?string $description,
        public readonly array $facts,
        public readonly array $components,
        public readonly array $actions,
        public readonly array $warnings = [],
    ) {}
}
