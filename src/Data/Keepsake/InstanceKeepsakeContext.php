<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Keepsake;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final readonly class InstanceKeepsakeContext
{
    /**
     * @param  list<array{reference:string,model:Model}>  $users
     * @param  list<array{reference:string,model:Model}>  $vouchers
     * @param  list<string>  $includes
     */
    public function __construct(
        public array $users,
        public array $vouchers,
        public array $includes,
        public string $currency,
        public bool $includePersonalData,
        public bool $includeLocationData,
        public bool $allowIncomplete,
        public bool $materializeArtifacts,
        public string $observedAt,
    ) {}

    public function includes(string $key): bool
    {
        return in_array($key, $this->includes, true);
    }

    public function userReference(Model $model): string
    {
        foreach ($this->users as $user) {
            if ($this->sameModel($user['model'], $model)) {
                return $user['reference'];
            }
        }

        throw new RuntimeException('The keepsake user reference could not be resolved.');
    }

    public function voucherReference(Model $model): string
    {
        foreach ($this->vouchers as $voucher) {
            if ($this->sameModel($voucher['model'], $model)) {
                return $voucher['reference'];
            }
        }

        throw new RuntimeException('The keepsake Pay Code reference could not be resolved.');
    }

    public function voucherReferenceByKey(string $modelType, string|int $modelKey): ?string
    {
        foreach ($this->vouchers as $voucher) {
            if ($voucher['model']->getMorphClass() === $modelType
                && (string) $voucher['model']->getKey() === (string) $modelKey) {
                return $voucher['reference'];
            }
        }

        return null;
    }

    private function sameModel(Model $left, Model $right): bool
    {
        return $left->getMorphClass() === $right->getMorphClass()
            && (string) $left->getKey() === (string) $right->getKey();
    }
}
