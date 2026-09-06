<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Bavix\Wallet\Interfaces\Wallet;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Services\SystemUserResolverService;

final class RequestScopedSystemUserResolver implements SystemUserResolverContract
{
    private ?Wallet $resolved = null;

    public function __construct(private readonly SystemUserResolverService $resolver) {}

    public function resolve(): Wallet
    {
        return $this->resolved ??= $this->resolver->resolve();
    }
}
