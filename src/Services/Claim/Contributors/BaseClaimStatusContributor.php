<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim\Contributors;

use LBHurtado\XChange\Contracts\Claim\ClaimSurfaceContributor;
use LBHurtado\XChange\Data\Claim\ClaimSurfaceContextData;
use LBHurtado\XChange\Services\Claim\ClaimSurfaceBuilder;

/**
 * Server-side counterpart to the friendly status copy already used in
 * `xrayClaimPreviewViewModel.ts`. Presentation only -- the state key/label
 * itself is untouched, this only decides the human headline/description
 * shown above whatever components later contributors add.
 */
final class BaseClaimStatusContributor implements ClaimSurfaceContributor
{
    private const COPY = [
        'active' => [
            'headline' => 'Ready to claim',
            'description' => 'This Pay Code is ready to be claimed.',
        ],
        'partially_claimed' => [
            'headline' => 'Partially claimed',
            'description' => 'Part of this Pay Code has already been claimed. The remaining balance can still be claimed.',
        ],
        'redeemed' => [
            'headline' => 'Already claimed',
            'description' => 'This Pay Code has already been fully claimed.',
        ],
        'expired' => [
            'headline' => 'Expired',
            'description' => 'This Pay Code is no longer available to claim.',
        ],
        'cancelled' => [
            'headline' => 'Cancelled',
            'description' => 'This Pay Code was cancelled by the issuer.',
        ],
        'payout_pending' => [
            'headline' => 'Payout in progress',
            'description' => 'The claim was recorded and the payout is being processed.',
        ],
        'payout_rejected' => [
            'headline' => 'Payout needs attention',
            'description' => 'The payout for this claim could not be completed.',
        ],
        'paid' => [
            'headline' => 'Paid',
            'description' => 'This Pay Code has been claimed and paid out.',
        ],
        'awaiting_approval' => [
            'headline' => 'Awaiting approval',
            'description' => 'This claim needs issuer approval before it can continue.',
        ],
        'closed' => [
            'headline' => 'Closed',
            'description' => 'This Pay Code is closed and can no longer be claimed.',
        ],
        'scheduled' => [
            'headline' => 'Not yet available',
            'description' => 'This Pay Code will become claimable later.',
        ],
        'locked' => [
            'headline' => 'Locked',
            'description' => 'This Pay Code is temporarily locked.',
        ],
    ];

    public function contribute(ClaimSurfaceBuilder $surface, ClaimSurfaceContextData $context): void
    {
        $copy = self::COPY[$context->state->key] ?? [
            'headline' => $context->state->label,
            'description' => null,
        ];

        $surface
            ->setVisibility('public_preview')
            ->setHeadline($copy['headline'])
            ->setDescription($copy['description'])
            ->addFact('code', $context->code);
    }
}
