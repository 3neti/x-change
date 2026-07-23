<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

use LBHurtado\XChange\Enums\FundingIntentStatus;
use Spatie\LaravelData\Data;

class FundingIntentTransitionData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public FundingIntentStatus $status,
        public string $eventType,
        public string $actorType,
        public string $actorId,
        public ?int $expectedVersion = null,
        public ?string $evidenceReference = null,
        public ?int $providerObservationId = null,
        public ?string $providerTransactionId = null,
        public array $metadata = [],
    ) {}
}
