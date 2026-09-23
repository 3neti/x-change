<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use LBHurtado\XChange\Enums\PolicyCompletionOutcomeStatus;
use SensitiveParameter;

final readonly class PolicyCompletionOutcomeData
{
    /**
     * @param  array<string, int|string|bool|null>  $safeResult
     * @param  array<string, mixed>  $privateResult
     */
    public function __construct(
        public PolicyCompletionOutcomeStatus $status,
        public string $resultCode,
        public ?string $providerReference = null,
        public array $safeResult = [],
        #[SensitiveParameter]
        public array $privateResult = [],
    ) {}
}
