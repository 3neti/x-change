<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Feedback;

use Spatie\LaravelData\Data;

final class JournaledFeedbackDeliveryResultData extends Data
{
    /**
     * @param  list<string>  $journalEventTypes
     */
    public function __construct(
        public string $status,
        public string $channel,
        public string $maskedRoute,
        public string $runReference,
        public bool $sent = false,
        public bool $replayed = false,
        public ?string $deliveryId = null,
        public ?string $providerMessageId = null,
        public ?string $providerStatus = null,
        public array $journalEventTypes = [],
        public array $meta = [],
    ) {}
}
