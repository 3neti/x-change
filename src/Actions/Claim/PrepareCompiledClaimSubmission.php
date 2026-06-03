<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use Illuminate\Http\Request;
use LBHurtado\XChange\Data\CompiledClaimSubmissionData;
use LBHurtado\XChange\Support\Claim\CompiledClaimSessionKeys;

final class PrepareCompiledClaimSubmission
{
    public function handle(array $validated): CompiledClaimSubmissionData
    {
        $submission = CompiledClaimSubmissionData::fromValidated($validated);

        session()->put(
            CompiledClaimSessionKeys::SUBMISSION,
            $submission->toSessionPayload()
        );

        return $submission;
    }
}
