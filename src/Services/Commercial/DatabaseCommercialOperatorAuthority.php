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
    /**
     * @var array<string, array<string, true>>
     */
    private array $capabilityCache = [];

    private ?bool $storageReady = null;

    public function allows(Model $operator, CommercialOperatorCapability $capability): bool
    {
        if (! $this->storageReady()) {
            return false;
        }

        return isset($this->capabilitiesFor($operator)[$capability->value]);
    }

    private function storageReady(): bool
    {
        return $this->storageReady ??= Schema::hasTable('x_change_commercial_operator_authorizations');
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

        return $this->capabilityCache[$key] = CommercialOperatorAuthorization::query()
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
