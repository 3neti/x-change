<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\CompiledClaimSubmissionData;

final class ResolveVoucherForCompiledClaimSubmission
{
    public function handle(CompiledClaimSubmissionData $submission): ?Voucher
    {
        return Voucher::query()
            ->where('code', $submission->code)
            ->first();
    }
}
