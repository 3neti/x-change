<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support;

final readonly class LifecycleScenarioRunOptions
{
    public function __construct(
        public ?string $issuer = null,
        public ?string $wallet = null,
        public ?string $amount = null,
        public ?string $timeout = null,
        public ?string $poll = null,
        public ?string $maxPolls = null,
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
            amount: self::stringOrNull($options['amount'] ?? null),
            timeout: self::stringOrNull($options['timeout'] ?? null),
            poll: self::stringOrNull($options['poll'] ?? null),
            maxPolls: self::stringOrNull($options['max-polls'] ?? null),
            onlyAttempt: self::stringOrNull($options['only-attempt'] ?? null),
            noClaim: (bool) ($options['no-claim'] ?? false),
            json: (bool) ($options['json'] ?? false),
            acceptPending: (bool) ($options['accept-pending'] ?? false),
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
