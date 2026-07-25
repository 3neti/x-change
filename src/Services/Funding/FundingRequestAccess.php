<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use LBHurtado\XChange\Models\FundingRequest;

final class FundingRequestAccess
{
    public function isReviewer(Authenticatable $actor): bool
    {
        $reviewerIds = array_values(array_filter(array_map(
            static fn (mixed $id): string => trim((string) $id),
            (array) config('x-change.funding.requests.reviewer_ids', []),
        )));

        return $reviewerIds !== []
            && in_array((string) $actor->getAuthIdentifier(), $reviewerIds, true);
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeOwner(
        FundingRequest $fundingRequest,
        Authenticatable $actor,
    ): void {
        if (
            $fundingRequest->requester_type !== $actor::class
            || $fundingRequest->requester_id !== (string) $actor->getAuthIdentifier()
        ) {
            throw new AuthorizationException(
                'This Funding Request belongs to another Account.',
            );
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeReviewer(Authenticatable $actor): void
    {
        if (! $this->isReviewer($actor)) {
            throw new AuthorizationException(
                'Funding Request review access is not configured for this operator.',
            );
        }
    }
}
