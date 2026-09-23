<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Settlement;

use LBHurtado\XChange\Contracts\CampaignPolicyCompletionTransportReadinessContract;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionTransportReadinessData;
use LBHurtado\XChange\Models\PolicyCompletionRequest;

final readonly class CheckCampaignPolicyCompletionTransportReadiness
{
    public function __construct(
        private CampaignPolicyCompletionTransportReadinessContract $readiness,
    ) {}

    public function handle(PolicyCompletionRequest $request): PolicyCompletionTransportReadinessData
    {
        return $this->readiness->for($request);
    }
}
