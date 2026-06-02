<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use Illuminate\Http\Request;
use LBHurtado\XChange\Data\CompiledClaimSubmissionData;

final class PrepareCompiledClaimSubmission
{
    public function handle(array $validated): CompiledClaimSubmissionData
    {
        $submission = CompiledClaimSubmissionData::fromValidated($validated);

        session()->put(
            'compiled_claim_submission',
            $submission->toSessionPayload()
        );

        return $submission;
    }
}
