<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimWorkflowResolverContract;
use LBHurtado\XChange\Data\Claim\ClaimWorkflowInterpretationData;

final class ClaimWorkflowReadModelProjector
{
    public function __construct(
        private readonly ClaimWorkflowResolverContract $workflows,
    ) {}

    public function project(Voucher $voucher): ClaimWorkflowInterpretationData
    {
        try {
            return ClaimWorkflowInterpretationData::resolved(
                $this->workflows->resolve($voucher),
            );
        } catch (ValidationException) {
            return ClaimWorkflowInterpretationData::needsAttention();
        }
    }
}
