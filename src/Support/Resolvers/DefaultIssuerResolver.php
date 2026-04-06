<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Resolvers;

use LBHurtado\XChange\Contracts\IssuerResolverContract;

class DefaultIssuerResolver implements IssuerResolverContract
{
    public function resolve(array $context = []): mixed
    {
        $issuerId = data_get($context, 'issuer_id');

        if (! $issuerId) {
            return null;
        }

        $modelClass = $this->issuerModelClass();

        if (! $modelClass || ! class_exists($modelClass)) {
            return null;
        }

        return $modelClass::query()->find($issuerId);
    }

    protected function issuerModelClass(): ?string
    {
        $configured = config('x-change.onboarding.issuer_model');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $fallback = config('auth.providers.users.model');

        return is_string($fallback) && $fallback !== ''
            ? $fallback
            : null;
    }
}
