<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitOperatorIssuanceActivitySearchFilterData extends Data
{
    /**
     * @param  array<int, string>  $statuses
     * @param  array<int, string>  $handoffStatuses
     */
    public function __construct(
        public readonly ?string $search = null,
        public readonly array $statuses = [],
        public readonly array $handoffStatuses = [],
    ) {}

    /**
     * @param  array<int, string>|string|null  $statuses
     * @param  array<int, string>|string|null  $handoffStatuses
     */
    public static function normalize(
        ?string $search = null,
        array|string|null $statuses = null,
        array|string|null $handoffStatuses = null,
    ): self {
        $search = self::nullableString($search);

        return new self(
            search: $search,
            statuses: self::stringList($statuses),
            handoffStatuses: self::stringList($handoffStatuses),
        );
    }

    public function isEmpty(): bool
    {
        return $this->search === null
            && $this->statuses === []
            && $this->handoffStatuses === [];
    }

    private static function nullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<int, string>|string|null  $values
     * @return array<int, string>
     */
    private static function stringList(array|string|null $values): array
    {
        if ($values === null) {
            return [];
        }

        $values = is_array($values) ? $values : [$values];

        return collect($values)
            ->map(fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '')
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }
}
