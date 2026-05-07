<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support;

final readonly class LifecycleScenarioRunOptions
{
    public function __construct(
        public ?string $issuer = null,
        public ?string $wallet = null,
        public ?float $amount = null,
        public ?int $timeout = null,
        public ?int $poll = null,
        public ?int $maxPolls = null,
        public ?string $onlyAttempt = null,
        public bool $noClaim = false,
        public bool $json = false,
        public bool $acceptPending = false,
    ) {}

    public static function fromConsoleOptions(array $options): self
    {
        return new self(
            issuer: self::stringOrNull($options['issuer'] ?? null),
            wallet: self::stringOrNull($options['wallet'] ?? null),
            amount: self::floatOrNull($options['amount'] ?? null),
            timeout: self::intOrNull($options['timeout'] ?? null),
            poll: self::intOrNull($options['poll'] ?? null),
            maxPolls: self::intOrNull($options['max-polls'] ?? null),
            onlyAttempt: self::stringOrNull($options['only-attempt'] ?? null),
            noClaim: (bool) ($options['no-claim'] ?? false),
            json: (bool) ($options['json'] ?? false),
            acceptPending: (bool) ($options['accept-pending'] ?? false),
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function intOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || $value === null) {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private static function floatOrNull(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || $value === null) {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
