<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\FormFlowManager\Services\DriverService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimWorkflowResolverContract;
use LBHurtado\XChange\Data\Claim\CompiledVoucherClaimFlowData;
use LBHurtado\XChange\Support\Claim\ClaimExperiencePayload;
use LBHurtado\XChange\Support\Claim\FormFlowSplashSkipPolicy;

final class VoucherClaimFlowCompiler
{
    public function __construct(
        private readonly DriverService $drivers,
        private readonly ClaimExperienceCompiler $experiences,
        private readonly ClaimWorkflowResolverContract $workflows,
        private readonly FormFlowClaimWorkflowMutator $formFlowWorkflows,
        private readonly FormFlowSplashSkipPolicy $splashPolicy,
    ) {}

    public function compile(Voucher $voucher, ?string $authenticatedMobile = null): CompiledVoucherClaimFlowData
    {
        $experience = $this->experiences->compile($voucher);
        $workflow = $this->workflows->resolve($voucher);
        $payload = ClaimExperiencePayload::putIntoInstructions(
            $this->drivers->transform($voucher)->toArray(),
            $experience->toArray(),
        );
        $instructions = $this->formFlowWorkflows->apply(
            FormFlowInstructionsData::from($payload),
            $workflow,
            $authenticatedMobile,
        );

        return new CompiledVoucherClaimFlowData(
            experience: $experience,
            workflow: $workflow,
            instructions: FormFlowInstructionsData::from(
                $this->splashPolicy->apply($instructions->toArray()),
            ),
        );
    }
}
