<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use Spatie\LaravelData\Data;

final class CompiledVoucherClaimFlowData extends Data
{
    public function __construct(
        public ClaimExperienceData $experience,
        public ClaimWorkflowDescriptorData $workflow,
        public FormFlowInstructionsData $instructions,
    ) {}
}
