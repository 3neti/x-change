<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\Claim\ClaimViewerResolverContract;
use LBHurtado\XChange\Data\Claim\ClaimSurfaceViewerData;

/**
 * Authenticated alone is not authority. This resolver is deliberately
 * conservative: it only ever grants the `issuer` role when the existing
 * voucher `owner()` relation (a MorphTo, the same convention
 * `VoucherLifecycleService::authorizeCancellation()` already relies on)
 * matches the authenticated user. There is no existing, durable
 * redeemer-identity or admin-role convention in x-change to check against
 * yet, so both are left as future seams rather than guessed at here --
 * every other authenticated visitor is `other_authenticated`.
 */
class ClaimViewerResolver implements ClaimViewerResolverContract
{
    public function resolve(?Authenticatable $user, Voucher $voucher): ClaimSurfaceViewerData
    {
        if ($user === null) {
            return new ClaimSurfaceViewerData(role: 'guest', authenticated: false);
        }

        if ($this->isIssuer($user, $voucher)) {
            return new ClaimSurfaceViewerData(role: 'issuer', authenticated: true);
        }

        return new ClaimSurfaceViewerData(role: 'other_authenticated', authenticated: true);
    }

    private function isIssuer(Authenticatable $user, Voucher $voucher): bool
    {
        $owner = $voucher->owner;

        if (! $owner instanceof Model || ! $user instanceof Model) {
            return false;
        }

        return $owner->is($user);
    }
}
