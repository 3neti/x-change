<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Execution;

use Spatie\LaravelData\Data;

class ExecutionResultHandoffSummaryData extends Data
{
    /**
     * @param  array<string, ExecutionResultHandoffResultData>  $results
     */
    public function __construct(
        public readonly string $schema = 'x-change.execution-result-handoff-summary.v1',
        public readonly string $status = 'completed_non_blocking',
        public readonly ?string $execution_id = null,
        public readonly ?string $voucher_code = null,
        public readonly ?string $correlation_id = null,
        public readonly bool $blocks_execution = false,
        public readonly array $results = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toReportArray(): array
    {
        return [
            'schema' => $this->schema,
            'status' => $this->status,
            'execution_id' => $this->execution_id,
            'voucher_code' => $this->voucher_code,
            'correlation_id' => $this->correlation_id,
            'blocks_execution' => $this->blocks_execution,
            'journal' => $this->resultFor('journal'),
            'action' => $this->resultFor('action'),
            'feedback' => $this->resultFor('feedback'),
            'cockpit_activity' => $this->resultFor('cockpit_activity'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resultFor(string $key): ?array
    {
        $result = $this->results[$key] ?? null;

        return $result instanceof ExecutionResultHandoffResultData
            ? $result->toArray()
            : null;
    }
}
