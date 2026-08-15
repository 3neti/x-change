<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Provisioning;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XProvisioning\Contracts\ProvisioningActorGuardContract;

final readonly class XChangeProvisioningActorGuard implements ProvisioningActorGuardContract
{
    public function __construct(private SystemUserResolverContract $systemUsers) {}

    public function assertEligible(Model $actor): void
    {
        $system = $this->systemUsers->resolve();

        if ($system instanceof Model
            && $system->getMorphClass() === $actor->getMorphClass()
            && (string) $system->getKey() === (string) $actor->getKey()) {
            throw new DomainException('The non-interactive System Principal cannot be a provisioning maker or checker.');
        }
    }
}
