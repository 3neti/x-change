<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Claim\ClaimWorkflowDescriptorData;

interface ClaimWorkflowResolverContract
{
    /**
     * Resolve instructions to the existing typed journey descriptor, not a paid
     * status or execution decision. Custom drivers require an explicit mapping.
     *
     * @throws ValidationException When declared intent is unsupported or conflicting.
     */
    public function resolve(Voucher $voucher): ClaimWorkflowDescriptorData;
}
