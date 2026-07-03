<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;

class CockpitExecutionReadModelData extends Data
{
    /**
     * @param  array<int, array<string, mixed>>  $events
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        #[MapOutputName('execution_id')]
        public readonly ?string $executionId,
        public readonly string $status,
        public readonly ?string $driver = null,
        public readonly array $events = [],
        public readonly array $metadata = [],
        public readonly array $redactions = ['payloads' => 'not-loaded'],
        public readonly bool $authorized = false,
    ) {}
}
