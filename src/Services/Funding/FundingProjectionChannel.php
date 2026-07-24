<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Database\Eloquent\Model;

final class FundingProjectionChannel
{
    public function nameForOwner(Model $owner): string
    {
        return $this->nameForIdentity($owner::class, (string) $owner->getKey());
    }

    public function nameForIdentity(string $ownerType, string $ownerId): string
    {
        return 'x-change.funding.'.$this->token($ownerType, $ownerId);
    }

    public function authorizes(Model $owner, string $token): bool
    {
        return hash_equals(
            $this->token($owner::class, (string) $owner->getKey()),
            $token,
        );
    }

    private function token(string $ownerType, string $ownerId): string
    {
        $key = (string) config(
            'x-change.funding.broadcast_reference_hash_key',
            config('app.key'),
        );

        return hash_hmac('sha256', $ownerType."\0".$ownerId, $key);
    }
}
