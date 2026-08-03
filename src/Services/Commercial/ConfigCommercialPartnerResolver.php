<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Contracts\CommercialPartnerResolverContract;

final readonly class ConfigCommercialPartnerResolver implements CommercialPartnerResolverContract
{
    public function __construct(
        private SystemUserResolverContract $systemPrincipal,
    ) {}

    public function resolve(string $partnerReference): ?Model
    {
        $partnerReference = trim($partnerReference);

        if ($partnerReference === 'partner:direct') {
            $system = $this->systemPrincipal->resolve();

            return $system instanceof Model ? $system : null;
        }

        $definition = config(
            'x-change.commercial.partners.principals.'.$partnerReference,
        );

        if (! is_array($definition)) {
            return null;
        }

        $model = (string) ($definition['model'] ?? '');
        $column = (string) ($definition['column'] ?? 'id');
        $value = $definition['value'] ?? null;

        if ($model === '' || ! is_subclass_of($model, Model::class)
            || $column === '' || $value === null) {
            return null;
        }

        return $model::query()->where($column, $value)->first();
    }
}
