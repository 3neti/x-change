<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\XChange\Data\CompiledClaimPreparationResult;

final class PrepareCompiledClaim
{
    public function __construct(
        private readonly ReadCompiledClaimSubmission $readSubmission,
        private readonly ResolveVoucherForCompiledClaimSubmission $resolveVoucher,
        private readonly ValidateCompiledClaimVoucher $validateVoucher,
    ) {}

    public function handle(bool $forget = false): CompiledClaimPreparationResult
    {
        $submission = $this->readSubmission->handle(forget: $forget);

        if (! $submission) {
            return CompiledClaimPreparationResult::missingSubmission();
        }

        $voucher = $this->resolveVoucher->handle($submission);
        $error = $this->validateVoucher->handle($voucher);

        if ($error !== null) {
            return CompiledClaimPreparationResult::invalid(
                submission: $submission,
                voucher: $voucher,
                errorMessage: $error,
            );
        }

        return CompiledClaimPreparationResult::valid(
            submission: $submission,
            voucher: $voucher,
        );
    }
}
