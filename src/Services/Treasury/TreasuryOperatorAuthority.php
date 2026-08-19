<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Models\TreasuryOperatorAuthorization;
use Throwable;

final readonly class TreasuryOperatorAuthority
{
    public function __construct(private SystemUserResolverContract $systemUsers) {}

    public function allows(Model $operator, TreasuryOperatorCapability $capability): bool
    {
        if (! Schema::hasTable('x_change_treasury_operator_authorizations')) {
            return false;
        }

        try {
            $system = $this->systemUsers->resolve();
        } catch (Throwable) {
            return false;
        }

        if ($system instanceof Model
            && $system->getMorphClass() === $operator->getMorphClass()
            && (string) $system->getKey() === (string) $operator->getKey()) {
            return false;
        }

        return TreasuryOperatorAuthorization::query()
            ->where('operator_type', $operator->getMorphClass())
            ->where('operator_id', $operator->getKey())
            ->where('capability', $capability->value)
            ->currentlyValid()
            ->exists();
    }

    public function assertAllows(Model $operator, TreasuryOperatorCapability $capability): void
    {
        abort_unless($this->allows($operator, $capability), 403);
    }
}
