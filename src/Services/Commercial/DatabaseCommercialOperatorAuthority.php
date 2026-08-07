<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;

final class DatabaseCommercialOperatorAuthority implements CommercialOperatorAuthorityContract
{
    public function allows(Model $operator, CommercialOperatorCapability $capability): bool
    {
        if (! Schema::hasTable('x_change_commercial_operator_authorizations')) {
            return false;
        }

        return CommercialOperatorAuthorization::query()
            ->where('operator_type', $operator->getMorphClass())
            ->where('operator_id', $operator->getKey())
            ->where('capability', $capability->value)
            ->currentlyValid()
            ->exists();
    }
}
