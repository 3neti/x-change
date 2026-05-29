<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

final class ClaimExperiencePayload
{
    public static function fromState(array $state): ?array
    {
        return data_get($state, 'claim_experience')
            ?? data_get($state, 'metadata.claim_experience')
            ?? data_get($state, 'instructions.metadata.claim_experience');
    }

    public static function putIntoInstructions(array $instructions, array $claimExperience): array
    {
        data_set($instructions, 'metadata.claim_experience', $claimExperience);

        return $instructions;
    }
}
