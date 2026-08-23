<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\LaravelData\Data;

class CockpitReadModelQueryData extends Data
{
    /**
     * @param  array<int, string>  $include
     */
    public function __construct(
        public readonly ?string $code = null,
        public readonly ?string $payCodeSearch = null,
        public readonly ?string $payCodeStatus = null,
        public readonly ?string $operatorId = null,
        public readonly ?string $operatorType = null,
        public readonly ?Authenticatable $actor = null,
        public readonly bool $canViewAllPayCodes = false,
        public readonly array $include = [],
        public readonly ?string $correlationId = null,
        public readonly ?CockpitOperatorIssuanceActivitySearchFilterData $operatorActivityFilters = null,
        public readonly ?string $campaignPlanningKey = null,
        public readonly ?string $campaignExecutionId = null,
        public readonly ?string $campaignId = null,
        public readonly ?string $campaignAudienceId = null,
        public readonly ?string $campaignRecipientId = null,
        public readonly ?string $campaignSource = null,
        public readonly ?string $campaignTemplateKey = null,
        public readonly int|float|string|null $campaignAmount = null,
        public readonly ?string $campaignCurrency = null,
        public readonly ?string $campaignRecipientReference = null,
        public readonly ?string $campaignPurpose = null,
    ) {}
}
