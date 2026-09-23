<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Settlement\PolicyCompletionPreparationData;
use LBHurtado\XChange\Models\CompletionClaimEvidenceProjection;

interface CampaignPolicyCompletionDriverContract
{
    public function driverId(): string;

    public function driverVersion(): string;

    /**
     * Prepare a memory-only insurer handoff without persistence or external effects.
     */
    public function prepare(
        CompletionClaimEvidenceProjection $projection,
    ): PolicyCompletionPreparationData;
}
