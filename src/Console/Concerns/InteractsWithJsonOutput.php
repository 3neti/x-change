<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Concerns;

trait InteractsWithJsonOutput
{
    protected function shouldOutputJson(): bool
    {
        return (bool) $this->option('json');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function renderPayload(array $payload, ?string $title = null): void
    {
        if ($this->shouldOutputJson()) {
            $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

            if ((bool) $this->option('pretty')) {
                $flags |= JSON_PRETTY_PRINT;
            }

            $this->line((string) json_encode($payload, $flags));

            return;
        }

        if ($title !== null && $title !== '' && ! (bool) $this->option('quiet')) {
            $this->info($title);
        }

        $this->renderStructuredPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function renderStructuredPayload(array $payload, string $prefix = ''): void
    {
        foreach ($payload as $key => $value) {
            $label = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                if ($value === []) {
                    $this->line(sprintf('%s: []', $label));

                    continue;
                }

                if ($this->isList($value)) {
                    $this->line(sprintf('%s:', $label));

                    foreach ($value as $index => $item) {
                        if (is_array($item)) {
                            $this->renderStructuredPayload($item, $label.'.'.$index);
                        } else {
                            $this->line(sprintf('  - %s', $this->stringifyConsoleValue($item)));
                        }
                    }

                    continue;
                }

                $this->renderStructuredPayload($value, $label);

                continue;
            }

            $this->line(sprintf('%s: %s', $label, $this->stringifyConsoleValue($value)));
        }
    }

    protected function stringifyConsoleValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        };
    }

    /**
     * @param  array<mixed>  $value
     */
    protected function isList(array $value): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
