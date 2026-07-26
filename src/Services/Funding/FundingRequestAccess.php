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
        return $this->isMaker($actor) || $this->isChecker($actor);
    }

    public function isMaker(Authenticatable $actor): bool
    {
        return $this->isConfiguredOperatorModel($actor)
            && in_array(
                (string) $actor->getAuthIdentifier(),
                $this->makerIds(),
                true,
            );
    }

    public function isChecker(Authenticatable $actor): bool
    {
        return $this->isConfiguredOperatorModel($actor)
            && in_array(
                (string) $actor->getAuthIdentifier(),
                $this->checkerIds(),
                true,
            );
    }

    /**
     * @return list<string>
     */
    public function makerIds(): array
    {
        return $this->configuredIds('maker_ids');
    }

    /**
     * @return list<string>
     */
    public function checkerIds(): array
    {
        return $this->configuredIds('checker_ids');
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeMaker(Authenticatable $actor): void
    {
        if (! $this->isMaker($actor)) {
            throw new AuthorizationException(
                'Funding Request maker access is not configured for this operator.',
            );
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeChecker(Authenticatable $actor): void
    {
        if (! $this->isChecker($actor)) {
            throw new AuthorizationException(
                'Funding Request checker access is not configured for this operator.',
            );
        }
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

    /**
     * @return list<string>
     */
    private function configuredIds(string $key): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string) $id),
            (array) config("x-change.funding.requests.{$key}", []),
        ))));

        if ($ids !== []) {
            return $ids;
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string) $id),
            (array) config('x-change.funding.requests.reviewer_ids', []),
        ))));
    }

    private function isConfiguredOperatorModel(Authenticatable $actor): bool
    {
        $modelClass = config('x-change.onboarding.issuer_model')
            ?: config('auth.providers.users.model');

        return is_string($modelClass)
            && $modelClass !== ''
            && is_a($actor, $modelClass);
    }
}
