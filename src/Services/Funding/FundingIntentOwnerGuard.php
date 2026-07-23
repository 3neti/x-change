<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use LBHurtado\XChange\Models\FundingIntent;

class FundingIntentOwnerGuard
{
    /**
     * @throws AuthorizationException
     */
    public function authorize(FundingIntent $intent, Authenticatable $owner): void
    {
        if ($intent->created_by_type !== $owner::class
            || ! hash_equals($intent->created_by_id, (string) $owner->getAuthIdentifier())) {
            throw new AuthorizationException('This Funding Intent belongs to another Account.');
        }
    }
}
