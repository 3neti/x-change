<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use Spatie\LaravelData\Data;

class ProviderReadinessData extends Data
{
    /**
     * @param  array<int, string>  $missing
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public bool $ready,
        public string $status,
        public string $provider,
        public string $topology,
        public ?string $mode = null,
        public ?string $reason = null,
        public ?int $linkId = null,
        public array $missing = [],
        public array $meta = [],
    ) {}

    /**
     * @param  array<int, string>  $missing
     * @param  array<string, mixed>  $meta
     */
    public static function blocked(
        string $provider,
        string $topology,
        ?string $mode,
        string $reason,
        array $missing = [],
        array $meta = [],
    ): self {
        return new self(
            ready: false,
            status: 'blocked',
            provider: $provider,
            topology: $topology,
            mode: $mode,
            reason: $reason,
            missing: $missing,
            meta: $meta,
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function ready(
        string $provider,
        string $topology,
        ?string $mode,
        ?int $linkId = null,
        array $meta = [],
    ): self {
        return new self(
            ready: true,
            status: 'ready',
            provider: $provider,
            topology: $topology,
            mode: $mode,
            linkId: $linkId,
            meta: $meta,
        );
    }
}
