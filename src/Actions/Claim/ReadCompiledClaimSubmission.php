<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\XChange\Data\CompiledClaimSubmissionData;
use LBHurtado\XChange\Support\Claim\CompiledClaimSessionKeys;

final class ReadCompiledClaimSubmission
{
    public function handle(bool $forget = false): ?CompiledClaimSubmissionData
    {
        $payload = session()->get(CompiledClaimSessionKeys::SUBMISSION);

        if (! is_array($payload)) {
            return null;
        }

        if (! isset($payload['code'], $payload['inputs']) || ! is_array($payload['inputs'])) {
            return null;
        }

        $submission = CompiledClaimSubmissionData::fromValidated($payload);

        if ($forget) {
            session()->forget(CompiledClaimSessionKeys::SUBMISSION);
        }

        return $submission;
    }
}
