<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Provisioning;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XChange\Models\ProvisioningOperatorAuthorization;

final readonly class ProvisioningOperatorAuthority
{
    public function __construct(private SystemUserResolverContract $systemUsers) {}

    public function allows(Model $operator, ProvisioningOperatorCapability $capability): bool
    {
        if (! Schema::hasTable('x_change_provisioning_operator_authorizations')) {
            return false;
        }

        $system = $this->systemUsers->resolve();

        if ($system instanceof Model && $operator->is($system)) {
            return false;
        }

        return ProvisioningOperatorAuthorization::query()
            ->where('operator_type', $operator->getMorphClass())
            ->where('operator_id', $operator->getKey())
            ->where('capability', $capability->value)
            ->currentlyValid()
            ->exists();
    }

    public function assertAllows(Model $operator, ProvisioningOperatorCapability $capability): void
    {
        abort_unless($this->allows($operator, $capability), 403);
    }
}
