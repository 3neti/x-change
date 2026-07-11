<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitIssuanceTemplateProfileData extends Data
{
    /**
     * @param  array<int, string>  $default_input_fields
     * @param  array<string, mixed>  $default_validation
     * @param  array<string, mixed>  $default_feedback
     * @param  array<string, mixed>  $default_rider
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $profile,
        public readonly bool $enabled = true,
        public readonly array $default_input_fields = [],
        public readonly array $default_validation = [],
        public readonly array $default_feedback = [],
        public readonly array $default_rider = [],
        public readonly array $metadata = [],
    ) {}
}
