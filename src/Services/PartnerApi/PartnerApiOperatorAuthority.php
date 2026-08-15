<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PartnerApi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Enums\PartnerApiOperatorCapability;
use LBHurtado\XChange\Models\PartnerApiOperatorAuthorization;

final class PartnerApiOperatorAuthority
{
    public function allows(Model $operator, PartnerApiOperatorCapability $capability): bool
    {
        if (! Schema::hasTable('x_change_partner_api_operator_authorizations')) {
            return false;
        }

        return PartnerApiOperatorAuthorization::query()
            ->where('operator_type', $operator->getMorphClass())
            ->where('operator_id', $operator->getKey())
            ->where('capability', $capability->value)
            ->currentlyValid()
            ->exists();
    }

    public function mayView(Model $operator): bool
    {
        return collect(PartnerApiOperatorCapability::cases())
            ->contains(fn (PartnerApiOperatorCapability $capability): bool => $this->allows($operator, $capability));
    }
}
