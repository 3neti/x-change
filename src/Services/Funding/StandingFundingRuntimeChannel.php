<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Database\Eloquent\Model;

final class StandingFundingRuntimeChannel
{
    public function name(): string
    {
        return 'x-change.standing-funding.'.$this->token();
    }

    public function authorizes(Model $user, string $token): bool
    {
        return $user->exists && hash_equals($this->token(), $token);
    }

    private function token(): string
    {
        return hash_hmac(
            'sha256',
            (string) config('x-change.instance.id', 'unconfigured'),
            (string) config('x-change.funding.broadcast_reference_hash_key', config('app.key')),
        );
    }
}
