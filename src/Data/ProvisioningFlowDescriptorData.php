<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use Spatie\LaravelData\Data;

class ProvisioningFlowDescriptorData extends Data
{
    /**
     * @param  array<int, string>  $steps
     * @param  array<int, string>  $fields
     * @param  array<int, string>  $actions
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $provider,
        public string $topology,
        public string $mode,
        public string $title,
        public string $description = '',
        public array $steps = [],
        public array $fields = [],
        public array $actions = [],
        public array $metadata = [],
    ) {}
}
