<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Scenarios;

use InvalidArgumentException;

final class LifecycleScenarioRepository
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $scenarios = config('x-change.lifecycle.scenarios', []);

        if (! is_array($scenarios)) {
            throw new InvalidArgumentException('Lifecycle scenarios config must be an array.');
        }

        return $scenarios;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function findOrFail(string $scenarioKey): array
    {
        $scenarios = $this->all();

        if (! array_key_exists($scenarioKey, $scenarios)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown scenario: %s',
                $scenarioKey,
            ));
        }

        $scenario = $scenarios[$scenarioKey];

        if (! is_array($scenario)) {
            throw new InvalidArgumentException(sprintf(
                'Lifecycle scenario [%s] must be an array.',
                $scenarioKey,
            ));
        }

        return $scenario;
    }

    /**
     * Mirrors RunLifecycleScenarioCommand::normalizeScenarioAttempts()
     *
     * @param  array<string, mixed>  $scenario
     * @return array<string, array<string, mixed>>
     */
    public function attemptsFor(array $scenario, ?string $selectedAttempt = null): array
    {
        $attempts = data_get($scenario, 'attempts');

        if (is_array($attempts) && $attempts !== []) {
            $normalized = $attempts;
        } else {
            $normalized = [
                'default' => [
                    'claim' => (array) data_get($scenario, 'claim', []),
                    'expect' => (array) data_get($scenario, 'expect', []),
                ],
            ];
        }

        if ($selectedAttempt === null || trim($selectedAttempt) === '') {
            return $normalized;
        }

        $selectedAttempt = trim($selectedAttempt);

        if (! array_key_exists($selectedAttempt, $normalized)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown attempt [%s]. Available attempts: %s',
                $selectedAttempt,
                implode(', ', array_keys($normalized)),
            ));
        }

        return [
            $selectedAttempt => $normalized[$selectedAttempt],
        ];
    }

    public function modeFor(array $scenario): string
    {
        return (string) data_get($scenario, 'mode', 'default');
    }

    public function labelFor(string $scenarioKey, array $scenario): string
    {
        return (string) data_get($scenario, 'label', $scenarioKey);
    }
}
