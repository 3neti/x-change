<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PartnerApi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Enums\PartnerApiOperatorCapability;
use LBHurtado\XChange\Models\PartnerApiOperatorAuthorization;

final class PartnerApiOperatorAuthority
{
    /**
     * @var array<string, array<string, true>>
     */
    private array $capabilityCache = [];

    private ?bool $storageReady = null;

    public function allows(Model $operator, PartnerApiOperatorCapability $capability): bool
    {
        if (! $this->storageReady()) {
            return false;
        }

        return isset($this->capabilitiesFor($operator)[$capability->value]);
    }

    public function mayView(Model $operator): bool
    {
        return collect(PartnerApiOperatorCapability::cases())
            ->contains(fn (PartnerApiOperatorCapability $capability): bool => $this->allows($operator, $capability));
    }

    private function storageReady(): bool
    {
        return $this->storageReady ??= Schema::hasTable('x_change_partner_api_operator_authorizations');
    }

    /**
     * @return array<string, true>
     */
    private function capabilitiesFor(Model $operator): array
    {
        $key = $this->operatorCacheKey($operator);

        if (array_key_exists($key, $this->capabilityCache)) {
            return $this->capabilityCache[$key];
        }

        return $this->capabilityCache[$key] = PartnerApiOperatorAuthorization::query()
            ->where('operator_type', $operator->getMorphClass())
            ->where('operator_id', $operator->getKey())
            ->currentlyValid()
            ->pluck('capability')
            ->mapWithKeys(fn (string $capability): array => [$capability => true])
            ->all();
    }

    private function operatorCacheKey(Model $operator): string
    {
        return implode(':', [
            $operator->getMorphClass(),
            (string) $operator->getKey(),
        ]);
    }
}
