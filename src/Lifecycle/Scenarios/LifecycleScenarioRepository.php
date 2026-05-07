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

        $normalized = [];

        foreach ($scenarios as $key => $scenario) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Lifecycle scenario keys must be strings.');
            }

            if (! is_array($scenario)) {
                throw new InvalidArgumentException(sprintf(
                    'Lifecycle scenario [%s] must be an array.',
                    $key,
                ));
            }

            $normalized[$key] = $this->normalize($key, $scenario);
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $scenarioKey): ?array
    {
        $scenarios = $this->all();

        return $scenarios[$scenarioKey] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function findOrFail(string $scenarioKey): array
    {
        $scenario = $this->find($scenarioKey);

        if ($scenario === null) {
            throw new InvalidArgumentException(sprintf(
                'Unknown scenario: %s',
                $scenarioKey,
            ));
        }

        return $scenario;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function byCategory(string $category): array
    {
        return array_filter(
            $this->all(),
            fn (array $scenario): bool => ($scenario['category'] ?? null) === $category,
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function byTag(string $tag): array
    {
        return array_filter(
            $this->all(),
            fn (array $scenario): bool => in_array($tag, $scenario['tags'] ?? [], true),
        );
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function groupedByCategory(): array
    {
        $grouped = [];

        foreach ($this->all() as $key => $scenario) {
            $category = (string) ($scenario['category'] ?? 'smoke');

            $grouped[$category][$key] = $scenario;
        }

        ksort($grouped);

        return $grouped;
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

    /**
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed>
     */
    private function normalize(string $scenarioKey, array $scenario): array
    {
        $tags = data_get($scenario, 'tags', []);

        if (! is_array($tags)) {
            $tags = [$tags];
        }

        $scenario['key'] = (string) data_get($scenario, 'key', $scenarioKey);
        $scenario['label'] = $this->labelFor($scenarioKey, $scenario);
        $scenario['category'] = (string) data_get($scenario, 'category', 'smoke');

        // IMPORTANT:
        // Do not inject mode when it is absent.
        // Some scenarios rely on ScenarioRunnerResolver to infer sequential_claims.
        if (array_key_exists('mode', $scenario)) {
            $scenario['mode'] = $this->modeFor($scenario);
        }

        $scenario['tags'] = array_values(array_filter(
            array_map('strval', $tags),
            fn (string $tag): bool => trim($tag) !== '',
        ));

        $scenario['risk'] = (string) data_get($scenario, 'risk', 'medium');
        $scenario['description'] = (string) data_get($scenario, 'description', '');

        return $scenario;
    }
}
