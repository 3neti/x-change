<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Scenarios;

use InvalidArgumentException;

final class LifecycleScenarioGroupRepository
{
    /**
     * @var array<int, string>
     */
    private const CATEGORIES = [
        'smoke',
        'contract',
        'provider',
        'settlement',
        'reconciliation',
        'partner',
        'regression',
    ];

    public function __construct(
        private readonly LifecycleScenarioRepository $scenarios,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $groups = config('x-change.lifecycle.scenario_groups', []);

        if (! is_array($groups)) {
            throw new InvalidArgumentException('Lifecycle scenario groups config must be an array.');
        }

        $normalized = [];

        foreach ($groups as $key => $group) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Lifecycle scenario group keys must be strings.');
            }

            if (! is_array($group)) {
                throw new InvalidArgumentException(sprintf(
                    'Lifecycle scenario group [%s] must be an array.',
                    $key,
                ));
            }

            $normalized[$key] = $this->normalize($key, $group);
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
    public function find(string $groupKey): ?array
    {
        $groups = $this->all();

        if (isset($groups[$groupKey])) {
            return $groups[$groupKey];
        }

        if ($this->isCategory($groupKey)) {
            return [
                'key' => $groupKey,
                'label' => str($groupKey)->replace(['-', '_'], ' ')->title()->toString(),
                'description' => sprintf('Auto-generated lifecycle scenario group for category [%s].', $groupKey),
                'categories' => [$groupKey],
                'tags' => [],
                'scenarios' => [],
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function findOrFail(string $groupKey): array
    {
        $group = $this->find($groupKey);

        if ($group === null) {
            throw new InvalidArgumentException(sprintf(
                'Unknown lifecycle scenario group: %s',
                $groupKey,
            ));
        }

        return $group;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function scenariosFor(string $groupKey): array
    {
        $group = $this->findOrFail($groupKey);

        $selected = [];

        foreach ($group['categories'] as $category) {
            $selected = array_replace($selected, $this->scenarios->byCategory((string) $category));
        }

        foreach ($group['tags'] as $tag) {
            $selected = array_replace($selected, $this->scenarios->byTag((string) $tag));
        }

        foreach ($group['scenarios'] as $scenarioKey) {
            $scenarioKey = (string) $scenarioKey;
            $scenario = $this->scenarios->find($scenarioKey);

            if ($scenario === null) {
                throw new InvalidArgumentException(sprintf(
                    'Lifecycle scenario group [%s] references unknown scenario [%s].',
                    $groupKey,
                    $scenarioKey,
                ));
            }

            $selected[$scenarioKey] = $scenario;
        }

        return $selected;
    }

    private function isCategory(string $key): bool
    {
        return in_array($key, self::CATEGORIES, true);
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    private function normalize(string $key, array $group): array
    {
        return [
            'key' => (string) data_get($group, 'key', $key),
            'label' => (string) data_get(
                $group,
                'label',
                str($key)->replace(['-', '_'], ' ')->title()->toString(),
            ),
            'description' => (string) data_get($group, 'description', ''),
            'categories' => $this->normalizeList(data_get($group, 'categories', [])),
            'tags' => $this->normalizeList(data_get($group, 'tags', [])),
            'scenarios' => $this->normalizeList(data_get($group, 'scenarios', [])),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeList(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            $value = [$value];
        }

        return array_values(array_filter(
            array_map('strval', $value),
            fn (string $item): bool => trim($item) !== '',
        ));
    }
}
