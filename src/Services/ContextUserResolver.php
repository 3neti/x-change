<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\UserResolverContract;
use RuntimeException;

class ContextUserResolver implements UserResolverContract
{
    public function resolve(array $context = []): mixed
    {
        $modelClass = config('x-change.onboarding.issuer_model');

        if (! is_string($modelClass) || $modelClass === '') {
            throw new RuntimeException('No issuer model configured.');
        }

        if (! class_exists($modelClass)) {
            throw new RuntimeException(sprintf('Configured issuer model [%s] does not exist.', $modelClass));
        }

        $issuerId = data_get($context, 'issuer_id')
            ?? data_get($context, 'id')
            ?? data_get($context, 'metadata.issuer_id');

        if ($issuerId !== null && $issuerId !== '') {
            return $modelClass::query()->find($issuerId);
        }

        $email = data_get($context, 'email')
            ?? data_get($context, 'metadata.issuer_email');

        if (is_string($email) && trim($email) !== '') {
            return $modelClass::query()->where('email', trim($email))->first();
        }

        $mobile = data_get($context, 'mobile')
            ?? data_get($context, 'metadata.issuer_mobile');

        if (is_string($mobile) && trim($mobile) !== '') {
            return $modelClass::query()->where('mobile', trim($mobile))->first();
        }

        return null;
    }
}
