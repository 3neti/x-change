<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners\Support;

final class SettlementPhaseSummary
{
    /**
     * @param  array<string, mixed>  $phases
     * @return array<string, int>
     */
    public function fromPhases(array $phases): array
    {
        $total = count($phases);

        $passed = collect($phases)
            ->filter(fn (array $phase): bool => (bool) data_get($phase, 'evaluation.passed', false))
            ->count();

        return [
            'passed' => $passed,
            'failed' => $total - $passed,
            'total' => $total,
        ];
    }

    /**
     * @param  array<string, mixed>  $attemptResults
     * @return array<string, int>
     */
    public function fromAttempts(array $attemptResults): array
    {
        $total = count($attemptResults);

        $passed = collect($attemptResults)
            ->filter(fn (array $result): bool => (bool) data_get($result, 'evaluation.passed', false))
            ->count();

        return [
            'passed' => $passed,
            'failed' => $total - $passed,
            'total' => $total,
        ];
    }
}
