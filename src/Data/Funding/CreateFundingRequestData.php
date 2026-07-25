<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

use DateTimeImmutable;
use LBHurtado\XChange\Enums\FundingRequestType;
use Spatie\LaravelData\Data;

final class CreateFundingRequestData extends Data
{
    public function __construct(
        public readonly string $accountReference,
        public readonly string $requesterType,
        public readonly string $requesterId,
        public readonly FundingRequestType $fundingType,
        public readonly int $requestedValueMinor,
        public readonly string $currency,
        public readonly string $description,
        public readonly string $idempotencyKey,
        public readonly ?string $externalReference = null,
        public readonly ?DateTimeImmutable $occurredOn = null,
        public readonly ?string $requesterNotes = null,
    ) {}
}
