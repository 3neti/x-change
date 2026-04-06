<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Facades\Auth;
use LBHurtado\XChange\Contracts\UserResolverContract;

class AuthUserResolver implements UserResolverContract
{
    public function resolve(array $context = []): mixed
    {
        return Auth::user();
    }
}
