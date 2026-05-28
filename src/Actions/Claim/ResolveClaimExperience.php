<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Claim\ClaimExperienceData;
use LBHurtado\XChange\Services\Claim\ClaimExperienceCompiler;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveClaimExperience
{
    use AsAction;

    public function handle(Voucher $voucher): ClaimExperienceData
    {
        return app(ClaimExperienceCompiler::class)->compile($voucher);
    }
}
