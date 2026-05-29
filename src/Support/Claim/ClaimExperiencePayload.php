<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

final class ClaimExperiencePayload
{
    public const REDIRECT_OWNER_CLAIM_WIDGET = 'claim-widget';

    public const SPLASH_OWNER_X_RIDER = 'x-rider';

    public const SPLASH_OWNER_FORM_FLOW = 'form-flow';

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

    public static function isClaimWidgetRedirect(array $claimExperience): bool
    {
        return self::redirectOwner($claimExperience) === self::REDIRECT_OWNER_CLAIM_WIDGET;
    }

    public static function redirectOwner(array $claimExperience): ?string
    {
        return data_get($claimExperience, 'diagnostics.redirect_owner');
    }

    public static function redirect(array $claimExperience): array
    {
        $owner = self::redirectOwner($claimExperience);

        return [
            'show_countdown' => self::isClaimWidgetRedirect($claimExperience)
                && (bool) data_get($claimExperience, 'options.show_redirect_countdown', false),
            'owner' => $owner,
            'delay_seconds' => collect(data_get($claimExperience, 'phases', []))
                ->firstWhere('key', 'redirect')['delay_seconds'] ?? null,
        ];
    }
}
