<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Redemption;

use Spatie\LaravelData\Data;

class RedemptionFlowData extends Data
{
    /**
     * @param  array<int, string>  $step_names
     * @param  array<string, string>  $step_handlers
     * @param  array<string, mixed>|null  $flow_instructions
     */
    public function __construct(
        public string $driver_name,
        public ?string $driver_version,
        public ?string $reference_id_template,
        public ?string $on_complete_callback,
        public ?string $on_cancel_callback,
        public array $step_names,
        public array $step_handlers,
        public ?array $flow_instructions,
    ) {}
}
