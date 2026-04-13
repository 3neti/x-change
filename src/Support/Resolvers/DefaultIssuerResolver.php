<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Resolvers;

use LBHurtado\XChange\Contracts\IssuerResolverContract;

class DefaultIssuerResolver implements IssuerResolverContract
{
    public function resolve(array $context = []): mixed
    {
        $modelClass = $this->issuerModelClass();

        if (! $modelClass || ! class_exists($modelClass)) {
            return null;
        }

        if ($issuerId = data_get($context, 'issuer_id')) {
            return $modelClass::query()->find($issuerId);
        }

        $query = $modelClass::query();

        if ($externalId = data_get($context, 'external_id')) {
            return $query->where('external_id', $externalId)->first();
        }

        if ($email = data_get($context, 'email')) {
            return $query->where('email', $email)->first();
        }

        if ($mobile = data_get($context, 'mobile')) {
            return $query->where('mobile', $mobile)->first();
        }

        return null;
    }
//    public function resolve(array $context = []): mixed
//    {
//        $issuerId = data_get($context, 'issuer_id');
//
//        if (! $issuerId) {
//            return null;
//        }
//
//        $modelClass = $this->issuerModelClass();
//
//        if (! $modelClass || ! class_exists($modelClass)) {
//            return null;
//        }
//
//        return $modelClass::query()->find($issuerId);
//    }

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
