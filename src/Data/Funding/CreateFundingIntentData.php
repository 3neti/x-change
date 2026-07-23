<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

use DateTimeImmutable;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use Spatie\LaravelData\Data;

class CreateFundingIntentData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $accountReference,
        public string $provider,
        public int $expectedAmountMinor,
        public string $currency,
        public string $idempotencyKey,
        public string $actorType,
        public string $actorId,
        public ?DateTimeImmutable $expiresAt = null,
        public array $metadata = [],
        public ?FundingDestinationData $destination = null,
    ) {}
}
