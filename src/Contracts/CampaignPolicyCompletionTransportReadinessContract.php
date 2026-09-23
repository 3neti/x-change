<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Settlement\PolicyCompletionTransportReadinessData;
use LBHurtado\XChange\Models\PolicyCompletionRequest;

interface CampaignPolicyCompletionTransportReadinessContract
{
    public function for(PolicyCompletionRequest $request): PolicyCompletionTransportReadinessData;
}
