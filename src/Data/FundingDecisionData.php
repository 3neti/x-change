<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use Spatie\LaravelData\Data;

class FundingDecisionData extends Data
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public bool $allowed,
        public string $authority,
        public int $available_minor,
        public int $required_minor,
        public string $currency = 'PHP',
        public ?string $fresh_as_of = null,
        public ?string $blocking_reason = null,
        public array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function allowed(
        string $authority,
        int $availableMinor,
        int $requiredMinor,
        string $currency = 'PHP',
        ?string $freshAsOf = null,
        array $meta = [],
    ): self {
        return new self(
            allowed: true,
            authority: $authority,
            available_minor: $availableMinor,
            required_minor: $requiredMinor,
            currency: $currency,
            fresh_as_of: $freshAsOf,
            meta: $meta,
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function blocked(
        string $authority,
        int $availableMinor,
        int $requiredMinor,
        string $reason,
        string $currency = 'PHP',
        ?string $freshAsOf = null,
        array $meta = [],
    ): self {
        return new self(
            allowed: false,
            authority: $authority,
            available_minor: $availableMinor,
            required_minor: $requiredMinor,
            currency: $currency,
            fresh_as_of: $freshAsOf,
            blocking_reason: $reason,
            meta: $meta,
        );
    }
}
