<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Execution;

use Spatie\LaravelData\Data;

class ExecutionResultHandoffResultData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $schema = 'x-change.execution-result-handoff.v1',
        public readonly string $target = 'unknown',
        public readonly string $status = 'not_wired',
        public readonly ?string $execution_id = null,
        public readonly ?string $voucher_code = null,
        public readonly ?string $correlation_id = null,
        public readonly bool $blocking = false,
        public readonly bool $performed_side_effect = false,
        public readonly string $source = 'null-execution-result-handoff',
        public readonly string $reason = 'Execution result handoff is not wired.',
        public readonly array $metadata = [],
    ) {}
}
