<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Scenarios;

final class LifecycleScenarioGroupRunResult
{
    /**
     * @param  array<string, LifecycleScenarioEngineResult>  $results
     * @param  array<int, array<string, mixed>>  $failures
     */
    public function __construct(
        public readonly string $group,
        public readonly array $results,
        public readonly array $failures = [],
    ) {}

    public function successful(): bool
    {
        if ($this->failures !== []) {
            return false;
        }

        foreach ($this->results as $result) {
            if ($result->exitCode !== 0) {
                return false;
            }
        }

        return true;
    }

    public function total(): int
    {
        return count($this->results) + count($this->failures);
    }

    public function passed(): int
    {
        return collect($this->results)
            ->filter(fn (LifecycleScenarioEngineResult $result): bool => $result->exitCode === 0)
            ->count();
    }

    public function failed(): int
    {
        return $this->total() - $this->passed();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'group' => $this->group,
            'successful' => $this->successful(),
            'summary' => [
                'total' => $this->total(),
                'passed' => $this->passed(),
                'failed' => $this->failed(),
            ],
            'results' => collect($this->results)
                ->map(fn (LifecycleScenarioEngineResult $result): array => [
                    'exit_code' => $result->exitCode,
                    'successful' => $result->exitCode === 0,
                    'payload' => $result->payload,
                ])
                ->all(),
            'failures' => $this->failures,
        ];
    }
}
