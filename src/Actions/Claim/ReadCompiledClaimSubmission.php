<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\XChange\Data\CompiledClaimSubmissionData;

final class ReadCompiledClaimSubmission
{
    public function handle(): ?CompiledClaimSubmissionData
    {
        $payload = session()->get('compiled_claim_submission');

        if (! is_array($payload)) {
            return null;
        }

        if (! isset($payload['code'], $payload['inputs']) || ! is_array($payload['inputs'])) {
            return null;
        }

        return CompiledClaimSubmissionData::fromValidated($payload);
    }
}
