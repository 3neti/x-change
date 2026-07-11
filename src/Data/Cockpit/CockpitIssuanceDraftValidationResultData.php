<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitIssuanceDraftValidationResultData extends Data
{
    /**
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly bool $valid,
        public readonly string $status,
        public readonly array $errors = [],
        public readonly array $warnings = [],
        public readonly array $metadata = [],
    ) {}
}
