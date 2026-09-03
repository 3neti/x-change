<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Models\TreasuryOperatorAuthorization;
use Throwable;

final class TreasuryOperatorAuthority
{
    /**
     * @var array<string, array<string, true>>
     */
    private array $capabilityCache = [];

    private ?bool $storageReady = null;

    private bool $systemResolved = false;

    private ?Model $system = null;

    public function __construct(private readonly SystemUserResolverContract $systemUsers) {}

    public function allows(Model $operator, TreasuryOperatorCapability $capability): bool
    {
        if (! $this->storageReady()) {
            return false;
        }

        if ($this->isSystemOperator($operator)) {
            return false;
        }

        return isset($this->capabilitiesFor($operator)[$capability->value]);
    }

    public function assertAllows(Model $operator, TreasuryOperatorCapability $capability): void
    {
        abort_unless($this->allows($operator, $capability), 403);
    }

    private function storageReady(): bool
    {
        return $this->storageReady ??= Schema::hasTable('x_change_treasury_operator_authorizations');
    }

    private function isSystemOperator(Model $operator): bool
    {
        $system = $this->system();

        return $system instanceof Model
            && $system->getMorphClass() === $operator->getMorphClass()
            && (string) $system->getKey() === (string) $operator->getKey();
    }

    private function system(): ?Model
    {
        if ($this->systemResolved) {
            return $this->system;
        }

        $this->systemResolved = true;

        try {
            $system = $this->systemUsers->resolve();
        } catch (Throwable) {
            return null;
        }

        return $this->system = $system instanceof Model ? $system : null;
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

        return $this->capabilityCache[$key] = TreasuryOperatorAuthorization::query()
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
