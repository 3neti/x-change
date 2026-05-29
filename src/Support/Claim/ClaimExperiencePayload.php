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

    public static function redirect(array $claimExperience): array
    {
        return [
            'show_countdown' => (bool) data_get($claimExperience, 'options.show_redirect_countdown', false),
            'owner' => data_get($claimExperience, 'diagnostics.redirect_owner'),
            'delay_seconds' => collect(data_get($claimExperience, 'phases', []))
                    ->firstWhere('key', 'redirect')['delay_seconds'] ?? null,
        ];
    }
}
