<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

final class IssueSystemAccountFundingPayCodeData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly int $amountMinor,
        public readonly string $connectionReference,
        public readonly string $idempotencyReference,
        public readonly Carbon $expiresAt,
        public readonly ?Model $recipient = null,
        public readonly ?string $evidenceReference = null,
        public readonly string $source = 'system_utility',
        public readonly array $metadata = [],
    ) {}
}
