<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Tests\Fakes;

use LBHurtado\XChange\Contracts\AuditLoggerContract;

class FakeAuditLogger implements AuditLoggerContract
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $entries = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $event, array $context = []): void
    {
        $this->entries[] = [
            'event' => $event,
            'context' => $context,
        ];
    }

    public function reset(): self
    {
        $this->entries = [];

        return $this;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function last(): ?array
    {
        return $this->entries === []
            ? null
            : $this->entries[array_key_last($this->entries)];
    }

    public function first(): ?array
    {
        return $this->entries === []
            ? null
            : $this->entries[0];
    }

    public function hasEvent(string $event): bool
    {
        foreach ($this->entries as $entry) {
            if (($entry['event'] ?? null) === $event) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function events(string $event): array
    {
        return array_values(array_filter(
            $this->entries,
            fn (array $entry): bool => ($entry['event'] ?? null) === $event
        ));
    }
}
